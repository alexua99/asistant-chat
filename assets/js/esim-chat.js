(function() {
    'use strict';
    
    let chatHistory = [];
    let isProcessing = false;
    let currentChatContainer = null;
    
    // Инициализация плавающей кнопки и модального окна
    function initFloatingButton() {
        const floatingBtn = document.getElementById('esim-chat-floating-btn');
        const modal = document.getElementById('esim-chat-modal');
        const closeBtn = document.getElementById('esim-chat-close');
        const overlay = document.querySelector('.esim-chat-modal-overlay');
        
        if (!floatingBtn || !modal) return;
        
        // Открытие модального окна
        floatingBtn.addEventListener('click', () => {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            // Инициализируем чат в модальном окне
            const modalContainer = modal.querySelector('#esim-chat-container');
            if (modalContainer && !modalContainer.dataset.initialized) {
                initChat(modalContainer);
                modalContainer.dataset.initialized = 'true';
            }
        });
        
        // Закрытие модального окна
        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        
        // Закрытие по ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });
    }
    
    function initChat(container) {
        if (!container) {
            container = document.getElementById('esim-chat-container');
        }
        if (!container) return;
        
        currentChatContainer = container;
        
        const input = container.querySelector('#esim-chat-input');
        const sendBtn = container.querySelector('#esim-chat-send');
        const toggleBtn = container.querySelector('#esim-chat-toggle');
        const widget = container.querySelector('#esim-chat-widget');
        
        if (!input || !sendBtn) return;
        
        // Отправка сообщения
        function sendMessage() {
            const text = input.value.trim();
            
            if (!text) return;
            if (isProcessing) return;
            
            const messageText = text;
            addMessage(text, 'user');
            
            input.value = '';
            sendBtn.disabled = true;
            isProcessing = true;
            
            showTyping();
            
            // Определяем способ отправки в зависимости от режима
            const mode = esimChatConfig.mode || 'openai';
            let requestPromise;
            
            if (mode === 'external') {
                // Режим внешнего сервера - отправляем напрямую
                requestPromise = fetch(esimChatConfig.apiUrl + '/chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: messageText,
                        history: chatHistory
                    })
                }).then(response => response.json());
            } else {
                // Режим OpenAI - отправляем через WordPress AJAX
                const formData = new FormData();
                formData.append('action', esimChatConfig.action);
                formData.append('nonce', esimChatConfig.nonce);
                formData.append('message', messageText);
                formData.append('history', JSON.stringify(chatHistory));
                
                requestPromise = fetch(esimChatConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());
            }
            
            requestPromise
            .then(data => {
                hideTyping();
                
                // Обработка ответа от WordPress AJAX
                if (data.success && data.data) {
                    const responseData = data.data;
                    
                    if (responseData.reply) {
                        addMessage(responseData.reply, 'assistant', true);
                        
                        if (responseData.followUp) {
                            // Wait for first message to finish typing before starting follow-up
                            const firstMessageLength = responseData.reply.length;
                            const delay = firstMessageLength * 15 + 500; // Calculate delay based on typing speed
                            setTimeout(() => {
                                addMessage(responseData.followUp, 'assistant', true);
                            }, delay);
                        }
                        
                        // Обновляем историю
                        if (text) {
                            chatHistory.push({ role: 'user', content: text });
                        }
                        chatHistory.push({ 
                            role: 'assistant', 
                            content: responseData.reply + (responseData.followUp ? ('\n' + responseData.followUp) : '')
                        });
                    } else {
                        addMessage('Error: No response from server', 'assistant', false);
                    }
                } 
                // Обработка прямого ответа от внешнего сервера
                else if (data.reply) {
                    addMessage(data.reply, 'assistant', true);
                    
                    if (data.followUp) {
                        // Wait for first message to finish typing before starting follow-up
                        const firstMessageLength = data.reply.length;
                        const delay = firstMessageLength * 15 + 500; // Calculate delay based on typing speed
                        setTimeout(() => {
                            addMessage(data.followUp, 'assistant', true);
                        }, delay);
                    }
                    
                    // Обновляем историю
                    if (text) {
                        chatHistory.push({ role: 'user', content: text });
                    }
                    chatHistory.push({ 
                        role: 'assistant', 
                        content: data.reply + (data.followUp ? ('\n' + data.followUp) : '')
                    });
                }
                // Error handling
                else if (data.success === false) {
                    const errorMsg = data.data?.message || 'An error occurred';
                    addMessage('Error: ' + errorMsg, 'assistant', false);
                } else {
                    addMessage('Error: No response from server', 'assistant', false);
                }
            })
            .catch(error => {
                hideTyping();
                console.error('Chat error:', error);
                addMessage('Connection error. Please check plugin settings.', 'assistant', false);
            })
            .finally(() => {
                sendBtn.disabled = false;
                isProcessing = false;
                input.focus();
            });
        }
        
        // Format text with Markdown using marked.js library
        function formatMessage(text) {
            if (!text) return '';
            
            // Check if marked.js is available
            if (typeof marked !== 'undefined') {
                try {
                    // Configure marked options for better formatting
                    marked.setOptions({
                        breaks: true, // Convert \n to <br>
                        gfm: true, // GitHub Flavored Markdown
                        headerIds: false, // Disable header IDs
                        mangle: false // Don't mangle email addresses
                    });
                    
                    // Parse Markdown to HTML
                    let html = marked.parse(text);
                    
                    // Additional cleanup and enhancements
                    // Ensure proper spacing
                    html = html.replace(/<p><\/p>/g, ''); // Remove empty paragraphs
                    html = html.replace(/\n\n+/g, '\n'); // Clean up extra newlines
                    
                    return html;
                } catch (e) {
                    console.warn('Marked.js parsing error:', e);
                    // Fallback to basic formatting if marked.js fails
                    return formatMessageBasic(text);
                }
            } else {
                // Fallback to basic formatting if marked.js is not loaded
                return formatMessageBasic(text);
            }
        }
        
        // Basic formatting fallback (original implementation)
        function formatMessageBasic(text) {
            if (!text) return '';
            
            let html = text;
            
            // Format code blocks
            html = html.replace(/```([^`]+)```/g, '<pre><code>$1</code></pre>');
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
            
            // Format bold text
            html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/__(.+?)__/g, '<strong>$1</strong>');
            
            // Format italic text
            html = html.replace(/([^*])\*([^*\n]+?)\*([^*])/g, '$1<em>$2</em>$3');
            html = html.replace(/([^_])_([^_\n]+?)_([^_])/g, '$1<em>$2</em>$3');
            
            // Format headers
            html = html.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
            html = html.replace(/^##\s+(.+)$/gm, '<h4>$1</h4>');
            
            // Format horizontal rules
            html = html.replace(/^[-*]{3,}$/gm, '<hr>');
            
            // Split by double line breaks
            let paragraphs = html.split(/\n\n+/);
            
            paragraphs = paragraphs.map(para => {
                para = para.trim();
                if (!para) return '';
                
                if (para.startsWith('<h3>') || para.startsWith('<h4>') || para.startsWith('<hr>')) {
                    return para;
                }
                
                const numberedItems = para.match(/^\d+\.\s+(.+)$/gm);
                if (numberedItems && numberedItems.length > 0) {
                    para = para.replace(/^\d+\.\s+(.+)$/gm, '<li>$1</li>');
                    para = para.replace(/(<li>.*?<\/li>(?:\s*<li>.*?<\/li>)*)/g, '<ol>$1</ol>');
                    return para;
                }
                
                const bulletItems = para.match(/^[-•*]\s+(.+)$/gm);
                if (bulletItems && bulletItems.length > 0) {
                    para = para.replace(/^[-•*]\s+(.+)$/gm, '<li>$1</li>');
                    para = para.replace(/(<li>.*?<\/li>(?:\s*<li>.*?<\/li>)*)/g, '<ul>$1</ul>');
                    return para;
                }
                
                if (para.startsWith('>')) {
                    para = para.replace(/^>\s*/gm, '');
                    para = para.replace(/\n/g, '<br>');
                    return '<blockquote>' + para + '</blockquote>';
                }
                
                para = para.replace(/\n/g, '<br>');
                return '<p>' + para + '</p>';
            });
            
            return paragraphs.filter(p => p).join('');
        }
        
        // Add message to chat with typewriter effect for assistant
        function addMessage(text, role, useTypewriter = false) {
            const messagesContainer = container.querySelector('#esim-chat-messages');
            if (!messagesContainer) return;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `esim-chat-message ${role}`;
            
            const bubble = document.createElement('div');
            bubble.className = 'esim-chat-message-bubble';
            
            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
            
            // For user messages, display immediately
            if (role === 'user') {
                bubble.textContent = text;
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                return;
            }
            
            // For assistant messages, use typewriter effect if enabled
            if (role === 'assistant' && useTypewriter) {
                typewriterEffect(bubble, text, messagesContainer);
            } else {
                // Format text for assistant messages
                bubble.innerHTML = formatMessage(text);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
        
        // Typewriter effect for smooth text display
        function typewriterEffect(element, text, container) {
            // First format the text to get final HTML structure
            const formattedHTML = formatMessage(text);
            
            // Create temporary element to extract plain text
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = formattedHTML;
            const plainText = tempDiv.textContent || tempDiv.innerText || '';
            
            // Store original HTML for final formatting
            const originalHTML = formattedHTML;
            
            // Display text character by character, then apply formatting at the end
            let charIndex = 0;
            const speed = 20; // milliseconds per character (adjust for speed: lower = faster)
            const minSpeed = 10;
            const maxSpeed = 30;
            
            // Variable speed based on character type
            function getCharSpeed(char) {
                if (char === ' ' || char === '\n') return minSpeed;
                if (/[.,!?;:]/.test(char)) return maxSpeed;
                return speed;
            }
            
            function typeNextChar() {
                if (charIndex < plainText.length) {
                    const currentText = plainText.substring(0, charIndex + 1);
                    const currentChar = plainText[charIndex];
                    
                    // Display plain text first (will be formatted at the end)
                    element.textContent = currentText;
                    container.scrollTop = container.scrollHeight;
                    
                    charIndex++;
                    const charSpeed = getCharSpeed(currentChar);
                    setTimeout(typeNextChar, charSpeed);
                } else {
                    // When done typing, apply full formatting
                    element.innerHTML = originalHTML;
                    container.scrollTop = container.scrollHeight;
                }
            }
            
            // Start typing
            typeNextChar();
        }
        
        // Показ индикатора печати
        let typingIndicator = null;
        function showTyping() {
            const messagesContainer = container.querySelector('#esim-chat-messages');
            if (!messagesContainer) return;
            
            typingIndicator = document.createElement('div');
            typingIndicator.className = 'esim-chat-message assistant';
            typingIndicator.innerHTML = '<div class="esim-chat-typing"><span></span><span></span><span></span></div>';
            messagesContainer.appendChild(typingIndicator);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        function hideTyping() {
            if (typingIndicator) {
                typingIndicator.remove();
                typingIndicator = null;
            }
        }
        
        // Обработчики событий
        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Сворачивание/разворачивание чата
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                widget.classList.toggle('collapsed');
                toggleBtn.textContent = widget.classList.contains('collapsed') ? '+' : '−';
            });
        }
        
        // Welcome message only if chat is not initialized and history is empty
        const messagesContainer = container.querySelector('#esim-chat-messages');
        const translations = esimChatConfig.translations || {};
        const welcomeMessage = translations.welcome_message || 'Hello! I\'m an eSIM consultant. How can I help?';
        
        if (messagesContainer && messagesContainer.children.length === 0 && chatHistory.length === 0) {
            setTimeout(() => {
                addMessage(welcomeMessage, 'assistant', true);
            }, 500);
        } else if (messagesContainer && messagesContainer.children.length === 0 && chatHistory.length > 0) {
            // Restore history from chatHistory (without typewriter for restored messages)
            chatHistory.forEach(msg => {
                if (msg.role && msg.content) {
                    addMessage(msg.content, msg.role, false);
                }
            });
        }
    }
    
    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initFloatingButton();
            // Инициализируем чат в шорткоде, если он есть
            const shortcodeContainer = document.querySelector('.esim-chat-shortcode');
            if (shortcodeContainer) {
                initChat(shortcodeContainer);
            }
        });
    } else {
        initFloatingButton();
        // Инициализируем чат в шорткоде, если он есть
        const shortcodeContainer = document.querySelector('.esim-chat-shortcode');
        if (shortcodeContainer) {
            initChat(shortcodeContainer);
        }
    }
})();

