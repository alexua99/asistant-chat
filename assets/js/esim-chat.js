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
            const email = container.querySelector('#esim-chat-email')?.value.trim() || '';
            const order = container.querySelector('#esim-chat-order')?.value.trim() || '';
            const iccid = container.querySelector('#esim-chat-iccid')?.value.trim() || '';
            
            if (!text && !email && !order && !iccid) return;
            if (isProcessing) return;
            
            const messageText = text || 'start';
            if (text) {
                addMessage(text, 'user');
            }
            
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
                        history: chatHistory,
                        email: email || undefined,
                        order: order || undefined,
                        iccid: iccid || undefined
                    })
                }).then(response => response.json());
            } else {
                // Режим OpenAI - отправляем через WordPress AJAX
                const formData = new FormData();
                formData.append('action', esimChatConfig.action);
                formData.append('nonce', esimChatConfig.nonce);
                formData.append('message', messageText);
                formData.append('email', email);
                formData.append('order', order);
                formData.append('iccid', iccid);
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
                        addMessage(responseData.reply, 'assistant');
                        
                        if (responseData.followUp) {
                            setTimeout(() => {
                                addMessage(responseData.followUp, 'assistant');
                            }, 500);
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
                        addMessage('Ошибка: нет ответа от сервера', 'assistant');
                    }
                } 
                // Обработка прямого ответа от внешнего сервера
                else if (data.reply) {
                    addMessage(data.reply, 'assistant');
                    
                    if (data.followUp) {
                        setTimeout(() => {
                            addMessage(data.followUp, 'assistant');
                        }, 500);
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
                // Обработка ошибок
                else if (data.success === false) {
                    const errorMsg = data.data?.message || 'Произошла ошибка';
                    addMessage('Ошибка: ' + errorMsg, 'assistant');
                } else {
                    addMessage('Ошибка: нет ответа от сервера', 'assistant');
                }
            })
            .catch(error => {
                hideTyping();
                console.error('Chat error:', error);
                addMessage('Ошибка соединения. Проверьте настройки плагина.', 'assistant');
            })
            .finally(() => {
                sendBtn.disabled = false;
                isProcessing = false;
                input.focus();
            });
        }
        
        // Добавление сообщения в чат
        function addMessage(text, role) {
            const messagesContainer = container.querySelector('#esim-chat-messages');
            if (!messagesContainer) return;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `esim-chat-message ${role}`;
            
            const bubble = document.createElement('div');
            bubble.className = 'esim-chat-message-bubble';
            bubble.textContent = text;
            
            messageDiv.appendChild(bubble);
            messagesContainer.appendChild(messageDiv);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
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
        const welcomeMessage = translations.welcome_message || 'Hello! I am an eSIM consultant. I can help with eSIM installation, setup, tell you about operators and devices. How can I help?';
        
        if (messagesContainer && messagesContainer.children.length === 0 && chatHistory.length === 0) {
            setTimeout(() => {
                addMessage(welcomeMessage, 'assistant');
            }, 500);
        } else if (messagesContainer && messagesContainer.children.length === 0 && chatHistory.length > 0) {
            // Restore history from chatHistory
            chatHistory.forEach(msg => {
                if (msg.role && msg.content) {
                    addMessage(msg.content, msg.role);
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

