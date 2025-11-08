<?php
/**
 * Plugin Name: eSIM Chat Assistant
 * Plugin URI: https://your-domain.com
 * Description: Интеграция eSIM чат-бота для консультаций по eSIM технологиям, операторам и устройствам
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-domain.com
 * License: GPL v2 or later
 * Text Domain: esim-chat
 */

// Запрещаем прямой доступ
if (!defined('ABSPATH')) {
    exit;
}

// Определяем константы плагина
define('ESIM_CHAT_VERSION', '1.0.0');
define('ESIM_CHAT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ESIM_CHAT_PLUGIN_URL', plugin_dir_url(__FILE__));

class ESIM_Chat {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_shortcode('esim_chat', array($this, 'render_chat_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_floating_button'));
        add_action('wp_ajax_esim_chat_send', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_esim_chat_send', array($this, 'handle_chat_request'));
        add_action('wp_ajax_esim_chat_test', array($this, 'handle_test_chat_request'));
    }
    
    public function init() {
        load_plugin_textdomain('esim-chat', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'eSIM Chat Settings',
            'eSIM Chat',
            'manage_options',
            'esim-chat',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function register_settings() {
        register_setting('esim_chat_settings', 'esim_chat_mode');
        register_setting('esim_chat_settings', 'esim_chat_api_url');
        register_setting('esim_chat_settings', 'esim_chat_openai_key');
        register_setting('esim_chat_settings', 'esim_chat_openai_model');
        register_setting('esim_chat_settings', 'esim_chat_enabled');
        register_setting('esim_chat_settings', 'esim_chat_display_type');
        register_setting('esim_chat_settings', 'esim_chat_language');
        register_setting('esim_chat_settings', 'esim_chat_response_scenarios');
        register_setting('esim_chat_settings', 'esim_chat_response_length');
        register_setting('esim_chat_settings', 'esim_chat_apn_data');
        register_setting('esim_chat_settings', 'esim_chat_ip_route_data');
        register_setting('esim_chat_settings', 'esim_chat_qa_data');
        
        add_settings_section(
            'esim_chat_main_section',
            'Main Settings',
            null,
            'esim-chat'
        );
        
        add_settings_section(
            'esim_chat_scenarios_section',
            'Response Scenarios',
            array($this, 'render_scenarios_section_description'),
            'esim-chat'
        );
        
        add_settings_field(
            'esim_chat_language',
            'Interface Language',
            array($this, 'render_language_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_mode',
            'Connection Mode',
            array($this, 'render_mode_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_openai_key',
            'OpenAI API Key',
            array($this, 'render_openai_key_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_openai_model',
            'AI Model',
            array($this, 'render_openai_model_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_api_url',
            'API Server URL (if "External Server" mode)',
            array($this, 'render_api_url_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_enabled',
            'Enable Chat',
            array($this, 'render_enabled_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_display_type',
            'Display Type',
            array($this, 'render_display_type_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_response_length',
            'Response Length',
            array($this, 'render_response_length_field'),
            'esim-chat',
            'esim_chat_main_section'
        );
        
        add_settings_field(
            'esim_chat_response_scenarios',
            'Response Scenarios',
            array($this, 'render_scenarios_field'),
            'esim-chat',
            'esim_chat_scenarios_section'
        );
        
        add_settings_section(
            'esim_chat_apn_section',
            'APN Data',
            array($this, 'render_apn_section_description'),
            'esim-chat'
        );
        
        add_settings_field(
            'esim_chat_apn_data',
            'APN Information',
            array($this, 'render_apn_data_field'),
            'esim-chat',
            'esim_chat_apn_section'
        );
        
        add_settings_field(
            'esim_chat_ip_route_data',
            'IP Route Information',
            array($this, 'render_ip_route_data_field'),
            'esim-chat',
            'esim_chat_apn_section'
        );
        
        add_settings_section(
            'esim_chat_qa_section',
            'Question & Answer Database',
            array($this, 'render_qa_section_description'),
            'esim-chat'
        );
        
        add_settings_field(
            'esim_chat_qa_data',
            'Q&A Pairs',
            array($this, 'render_qa_data_field'),
            'esim-chat',
            'esim_chat_qa_section'
        );
    }
    
    public function render_qa_section_description() {
        echo '<p>Add question and answer pairs. When users ask questions that match or are similar to your questions, the AI will use your provided answers.</p>';
    }
    
    public function render_qa_data_field() {
        $value = get_option('esim_chat_qa_data', '');
        $editor_id = 'esim_chat_qa_data';
        
        wp_editor(
            $value,
            $editor_id,
            array(
                'textarea_name' => 'esim_chat_qa_data',
                'textarea_rows' => 20,
                'media_buttons' => false,
                'teeny' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                    'toolbar2' => '',
                ),
            )
        );
        ?>
        <p class="description">
            <strong>How to format Q&A pairs:</strong><br>
            • Use format: <code>Question: Answer</code><br>
            • Example: <code>What is eSIM?: eSIM is an embedded SIM card that allows you to activate a cellular plan without a physical SIM card.</code><br>
            • Example: <code>How do I install eSIM?: To install eSIM, scan the QR code provided by your operator in your device settings.</code><br>
            • You can add multiple Q&A pairs, one per line or in a list format<br>
            • The AI will match user questions to your questions and use your answers when appropriate<br>
            • Use clear, specific questions that users might ask
        </p>
        <?php
    }
    
    public function render_ip_route_data_field() {
        $value = get_option('esim_chat_ip_route_data', '');
        $editor_id = 'esim_chat_ip_route_data';
        
        wp_editor(
            $value,
            $editor_id,
            array(
                'textarea_name' => 'esim_chat_ip_route_data',
                'textarea_rows' => 15,
                'media_buttons' => false,
                'teeny' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                    'toolbar2' => '',
                ),
            )
        );
        ?>
        <p class="description">
            <strong>How to format IP route data:</strong><br>
            • Use format: <code>Country/Operator: IP route</code><br>
            • Example: <code>Japan/Docomo: 10.0.0.0/8</code><br>
            • Example: <code>Japan/SoftBank: 192.168.0.0/16</code><br>
            • Example: <code>USA/AT&T: 172.16.0.0/12</code><br>
            • You can add multiple entries, one per line or in a list format<br>
            • The AI will use this information when users ask about IP route settings
        </p>
        <?php
    }
    
    public function render_apn_section_description() {
        echo '<p>Add APN (Access Point Name) and IP route information for different countries and operators. This information will be used by the AI when answering APN and IP route related questions.</p>';
    }
    
    public function render_apn_data_field() {
        $value = get_option('esim_chat_apn_data', '');
        $editor_id = 'esim_chat_apn_data';
        
        wp_editor(
            $value,
            $editor_id,
            array(
                'textarea_name' => 'esim_chat_apn_data',
                'textarea_rows' => 15,
                'media_buttons' => false,
                'teeny' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                    'toolbar2' => '',
                ),
            )
        );
        ?>
        <p class="description">
            <strong>How to format APN data:</strong><br>
            • Use format: <code>Country/Operator: APN name</code><br>
            • Example: <code>Japan/Docomo: spmode.ne.jp</code><br>
            • Example: <code>Japan/SoftBank: jp-d01.sbm.jp</code><br>
            • Example: <code>USA/AT&T: broadband</code><br>
            • You can add multiple entries, one per line or in a list format<br>
            • The AI will use this information when users ask about APN settings
        </p>
        <?php
    }
    
    public function render_scenarios_section_description() {
        echo '<p>Define custom response scenarios that the AI should follow. Use this to provide specific instructions, examples, or guidelines for how the AI should respond to certain topics or questions.</p>';
    }
    
    public function render_scenarios_field() {
        $value = get_option('esim_chat_response_scenarios', '');
        $editor_id = 'esim_chat_response_scenarios';
        
        wp_editor(
            $value,
            $editor_id,
            array(
                'textarea_name' => 'esim_chat_response_scenarios',
                'textarea_rows' => 15,
                'media_buttons' => false,
                'teeny' => true,
                'tinymce' => array(
                    'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                    'toolbar2' => '',
                ),
            )
        );
        ?>
        <p class="description">
            <strong>How to use scenarios:</strong><br>
            • Define specific instructions for how the AI should respond to certain topics<br>
            • Provide examples of good responses<br>
            • Set guidelines for handling specific situations<br>
            • Use format: <code>Topic/Keyword: Instructions or example response</code><br><br>
            <strong>Example:</strong><br>
            <code>
            Order Status: When users ask about order status, always ask for their order number first, then provide helpful information about checking their order.<br><br>
            Technical Support: For technical issues, provide step-by-step instructions and ask clarifying questions if needed.
            </code>
        </p>
        <?php
    }
    
    public function render_language_field() {
        $value = get_option('esim_chat_language', 'en');
        ?>
        <select name="esim_chat_language" id="esim_chat_language">
            <option value="en" <?php selected($value, 'en'); ?>>English</option>
            <option value="uk" <?php selected($value, 'uk'); ?>>Українська</option>
            <option value="ru" <?php selected($value, 'ru'); ?>>Русский</option>
        </select>
        <p class="description">Select the language for the chat interface</p>
        <?php
    }
    
    public function render_mode_field() {
        $value = get_option('esim_chat_mode', 'openai');
        ?>
        <select name="esim_chat_mode" id="esim_chat_mode" onchange="toggleApiUrlField()">
            <option value="openai" <?php selected($value, 'openai'); ?>>Direct OpenAI connection (recommended)</option>
            <option value="external" <?php selected($value, 'external'); ?>>External Server</option>
        </select>
        <p class="description">Choose connection method: direct OpenAI connection (no additional setup required) or external server (requires separate server for request processing)</p>
        <?php
    }
    
    public function render_openai_key_field() {
        $value = get_option('esim_chat_openai_key', '');
        echo '<input type="password" name="esim_chat_openai_key" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Your OpenAI API key (starts with sk-). Get it at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>';
    }
    
    public function render_openai_model_field() {
        $value = get_option('esim_chat_openai_model', 'gpt-4o-mini');
        ?>
        <select name="esim_chat_openai_model" id="esim_chat_openai_model">
            <option value="gpt-4o" <?php selected($value, 'gpt-4o'); ?>>GPT-4o (Most capable, best for complex tasks)</option>
            <option value="gpt-4o-mini" <?php selected($value, 'gpt-4o-mini'); ?>>GPT-4o Mini (Recommended, fast and efficient)</option>
            <option value="gpt-4-turbo" <?php selected($value, 'gpt-4-turbo'); ?>>GPT-4 Turbo (High quality, slower)</option>
            <option value="gpt-4" <?php selected($value, 'gpt-4'); ?>>GPT-4 (High quality, slower)</option>
            <option value="gpt-3.5-turbo" <?php selected($value, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Fast, lower cost)</option>
        </select>
        <p class="description">Select the AI model for chat conversations. GPT-4o Mini is recommended for best balance of quality and speed.</p>
        <?php
    }
    
    public function render_api_url_field() {
        $value = get_option('esim_chat_api_url', 'http://localhost:3000');
        echo '<div id="esim-chat-api-url-wrapper">';
        echo '<input type="url" name="esim_chat_api_url" id="esim_chat_api_url" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">URL of your external eSIM chat server (e.g., https://your-domain.com or http://localhost:3000). This field is only needed if you use "External Server" mode.</p>';
        echo '</div>';
    }
    
    public function render_enabled_field() {
        $value = get_option('esim_chat_enabled', '1');
        echo '<input type="checkbox" name="esim_chat_enabled" value="1" ' . checked('1', $value, false) . ' />';
        echo '<label>Enable chat display</label>';
    }
    
    public function render_display_type_field() {
        $value = get_option('esim_chat_display_type', 'button');
        ?>
        <select name="esim_chat_display_type">
            <option value="button" <?php selected($value, 'button'); ?>>Floating Button (recommended)</option>
            <option value="shortcode" <?php selected($value, 'shortcode'); ?>>Shortcode Only [esim_chat]</option>
        </select>
        <p class="description">Choose how to display the chat on your site: floating button will appear on all pages, shortcode - only where you place it</p>
        <?php
    }
    
    public function render_response_length_field() {
        $value = get_option('esim_chat_response_length', 'brief');
        ?>
        <select name="esim_chat_response_length" id="esim_chat_response_length">
            <option value="brief" <?php selected($value, 'brief'); ?>>Brief (recommended)</option>
            <option value="detailed" <?php selected($value, 'detailed'); ?>>Detailed</option>
        </select>
        <p class="description">Choose the length of AI responses: Brief - very short and concise answers (1-2 sentences), Detailed - longer answers with more information (2-3 sentences or short paragraph)</p>
        <?php
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('esim_chat_messages', 'esim_chat_message', 'Settings saved', 'updated');
        }
        
        settings_errors('esim_chat_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('esim_chat_settings');
                do_settings_sections('esim-chat');
                submit_button('Save Settings');
                ?>
            </form>
            
            <script>
            function toggleApiUrlField() {
                var mode = document.getElementById('esim_chat_mode').value;
                var apiUrlWrapper = document.getElementById('esim-chat-api-url-wrapper');
                var apiUrlRow = apiUrlWrapper ? apiUrlWrapper.closest('tr') : null;
                if (apiUrlRow) {
                    apiUrlRow.style.display = (mode === 'external') ? '' : 'none';
                }
            }
            // Call on page load
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', toggleApiUrlField);
            } else {
                toggleApiUrlField();
            }
            </script>
            
            <h2>Usage</h2>
            <p><strong>Floating Button:</strong> If "Floating Button" display type is selected, the chat will automatically appear on all pages as a button in the bottom right corner.</p>
            
            <p><strong>Shortcode:</strong> If "Shortcode Only" display type is selected, use the shortcode to display the chat on a page:</p>
            <code>[esim_chat]</code>
            
            <p>Or add to your theme template:</p>
            <code>&lt;?php echo do_shortcode('[esim_chat]'); ?&gt;</code>
            
            <p><strong>Note:</strong> The shortcode will work regardless of the selected display type, but the floating button will only show when the corresponding type is selected.</p>
            
            <hr>
            
            <h2>Test Chat</h2>
            <p>Test how the AI responds to questions. This is useful for checking the AI's behavior before making it available to users.</p>
            
            <div id="esim-chat-test-container" style="max-width: 800px; margin-top: 20px;">
                <div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 15px;">
                    <div id="esim-chat-test-messages" style="min-height: 200px; max-height: 400px; overflow-y: auto; margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                        <div style="color: #666; font-style: italic;">Test messages will appear here...</div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="esim-chat-test-input" placeholder="Enter your test question..." style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
                        <button type="button" id="esim-chat-test-send" class="button button-primary">Send</button>
                        <button type="button" id="esim-chat-test-clear" class="button">Clear</button>
                    </div>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                var testHistory = [];
                var isProcessing = false;
                
                function addTestMessage(text, role) {
                    var $messages = $('#esim-chat-test-messages');
                    if ($messages.find('div:first').text() === 'Test messages will appear here...') {
                        $messages.empty();
                    }
                    
                    var $message = $('<div style="margin-bottom: 15px; padding: 10px; border-radius: 4px; ' + 
                        (role === 'user' ? 'background: #e3f2fd; margin-left: 20%;' : 'background: #f5f5f5; margin-right: 20%;') + '">');
                    $message.append('<strong style="display: block; margin-bottom: 5px; color: #333;">' + (role === 'user' ? 'You' : 'AI') + ':</strong>');
                    $message.append('<div style="color: #333; white-space: pre-wrap;">' + $('<div>').text(text).html() + '</div>');
                    $messages.append($message);
                    $messages.scrollTop($messages[0].scrollHeight);
                }
                
                function sendTestMessage() {
                    var $input = $('#esim-chat-test-input');
                    var text = $input.val().trim();
                    
                    if (!text || isProcessing) return;
                    
                    addTestMessage(text, 'user');
                    testHistory.push({ role: 'user', content: text });
                    $input.val('');
                    
                    isProcessing = true;
                    $('#esim-chat-test-send').prop('disabled', true).text('Sending...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'esim_chat_test',
                            nonce: '<?php echo wp_create_nonce('esim_chat_test'); ?>',
                            message: text,
                            history: JSON.stringify(testHistory)
                        },
                        success: function(response) {
                            if (response.success && response.data && response.data.reply) {
                                addTestMessage(response.data.reply, 'assistant');
                                testHistory.push({ role: 'assistant', content: response.data.reply });
                            } else {
                                var errorMsg = 'Unknown error';
                                if (response.data && response.data.message) {
                                    errorMsg = response.data.message;
                                } else if (response.data) {
                                    errorMsg = JSON.stringify(response.data);
                                }
                                addTestMessage('Error: ' + errorMsg, 'assistant');
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = 'Could not connect to server';
                            if (xhr.responseText) {
                                try {
                                    var errorData = JSON.parse(xhr.responseText);
                                    if (errorData.data && errorData.data.message) {
                                        errorMsg = errorData.data.message;
                                    }
                                } catch(e) {
                                    errorMsg = 'Server error: ' + xhr.status + ' ' + error;
                                }
                            }
                            addTestMessage('Error: ' + errorMsg, 'assistant');
                        },
                        complete: function() {
                            isProcessing = false;
                            $('#esim-chat-test-send').prop('disabled', false).text('Send');
                        }
                    });
                }
                
                $('#esim-chat-test-send').on('click', sendTestMessage);
                $('#esim-chat-test-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        sendTestMessage();
                    }
                });
                $('#esim-chat-test-clear').on('click', function() {
                    $('#esim-chat-test-messages').html('<div style="color: #666; font-style: italic;">Test messages will appear here...</div>');
                    testHistory = [];
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function handle_test_chat_request() {
        // Check permissions - only admins can test
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'esim_chat_test')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $mode = get_option('esim_chat_mode', 'openai');
        
        if ($mode === 'external') {
            // For external server mode, just return error
            wp_send_json_error(array('message' => 'Test chat is only available for OpenAI mode'));
            return;
        }
        
        // Use the same logic as regular chat request
        $api_key = get_option('esim_chat_openai_key', '');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'OpenAI API key is not configured. Please set it in the plugin settings.'));
            return;
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        
        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is required'));
            return;
        }
        
        // Get user IP
        $user_ip = $this->get_user_ip();
        
        // Get system prompt
        $system_prompt = $this->get_system_prompt($user_ip);
        
        // Build messages array
        $messages = array(
            array('role' => 'system', 'content' => $system_prompt)
        );
        
        // Add history
        if (is_array($history) && !empty($history)) {
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = array(
                        'role' => sanitize_text_field($msg['role']),
                        'content' => sanitize_text_field($msg['content'])
                    );
                }
            }
        }
        
        // Add current message
        $messages[] = array('role' => 'user', 'content' => $message);
        
        // Get selected model
        $model = get_option('esim_chat_openai_model', 'gpt-4o-mini');
        
        // Send request to OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'OpenAI API connection error: ' . $response->get_error_message()));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            wp_send_json_error(array('message' => 'OpenAI API error: ' . ($data['error']['message'] ?? 'Unknown error')));
            return;
        }
        
        if (isset($data['choices'][0]['message']['content'])) {
            wp_send_json_success(array(
                'reply' => trim($data['choices'][0]['message']['content'])
            ));
        } else {
            wp_send_json_error(array('message' => 'Unexpected response format from OpenAI API'));
        }
    }
    
    private function get_translations($lang = 'en') {
        $translations = array(
            'en' => array(
                'title' => 'eSIM Consultant',
                'order_placeholder' => 'Order number (optional)',
                'input_placeholder' => 'Enter your question...',
                'send_button' => 'Send',
                'open_chat' => 'Open chat',
                'close_chat' => 'Close chat',
                'welcome_message' => 'Hello! I\'m an eSIM consultant. How can I help?'
            ),
            'uk' => array(
                'title' => 'eSIM Консультант',
                'order_placeholder' => 'Номер замовлення (опціонально)',
                'input_placeholder' => 'Введіть ваше питання...',
                'send_button' => 'Відправити',
                'open_chat' => 'Відкрити чат',
                'close_chat' => 'Закрити чат',
                'welcome_message' => 'Привіт! Я консультант з eSIM. Чим можу допомогти?'
            ),
            'ru' => array(
                'title' => 'eSIM Консультант',
                'order_placeholder' => 'Номер заказа (опционально)',
                'input_placeholder' => 'Введите ваш вопрос...',
                'send_button' => 'Отправить',
                'open_chat' => 'Открыть чат',
                'close_chat' => 'Закрыть чат',
                'welcome_message' => 'Привет! Я консультант по eSIM. Чем могу помочь?'
            )
        );
        
        return isset($translations[$lang]) ? $translations[$lang] : $translations['en'];
    }
    
    public function enqueue_scripts() {
        if (get_option('esim_chat_enabled', '1') !== '1') {
            return;
        }
        
        wp_enqueue_style('esim-chat-style', ESIM_CHAT_PLUGIN_URL . 'assets/css/esim-chat.css', array(), ESIM_CHAT_VERSION);
        
        // Enqueue marked.js for Markdown parsing
        wp_enqueue_script('marked', 'https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js', array(), '11.1.1', false);
        
        wp_enqueue_script('esim-chat-script', ESIM_CHAT_PLUGIN_URL . 'assets/js/esim-chat.js', array('marked'), ESIM_CHAT_VERSION, true);
        
        $mode = get_option('esim_chat_mode', 'openai');
        $api_url = get_option('esim_chat_api_url', 'http://localhost:3000');
        $language = get_option('esim_chat_language', 'en');
        $translations = $this->get_translations($language);
        
        wp_localize_script('esim-chat-script', 'esimChatConfig', array(
            'mode' => $mode,
            'apiUrl' => esc_url_raw($api_url),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('esim_chat_nonce'),
            'action' => 'esim_chat_send',
            'language' => $language,
            'translations' => $translations
        ));
    }
    
    public function render_chat_shortcode($atts) {
        if (get_option('esim_chat_enabled', '1') !== '1') {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px'
        ), $atts);
        
        $language = get_option('esim_chat_language', 'en');
        $translations = $this->get_translations($language);
        
        ob_start();
        ?>
        <div id="esim-chat-container" class="esim-chat-shortcode" style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
            <div id="esim-chat-widget">
                <div class="esim-chat-header">
                    <h3 data-translate="title"><?php echo esc_html($translations['title']); ?></h3>
                    <button id="esim-chat-toggle" class="esim-chat-toggle-btn">−</button>
                </div>
                <div id="esim-chat-messages" class="esim-chat-messages"></div>
                <div class="esim-chat-input-area">
                    <div class="esim-chat-input-wrapper">
                        <input type="text" id="esim-chat-input" data-translate-placeholder="input_placeholder" placeholder="<?php echo esc_attr($translations['input_placeholder']); ?>" />
                        <button id="esim-chat-send" class="esim-chat-send-btn" data-translate="send_button"><?php echo esc_html($translations['send_button']); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function render_floating_button() {
        if (get_option('esim_chat_enabled', '1') !== '1') {
            return;
        }
        
        $display_type = get_option('esim_chat_display_type', 'button');
        if ($display_type !== 'button') {
            return;
        }
        
        $language = get_option('esim_chat_language', 'en');
        $translations = $this->get_translations($language);
        ?>
        <!-- Floating chat button -->
        <button id="esim-chat-floating-btn" class="esim-chat-floating-btn" aria-label="<?php echo esc_attr($translations['open_chat']); ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2Z" fill="currentColor"/>
            </svg>
        </button>
        
        <!-- Chat modal window -->
        <div id="esim-chat-modal" class="esim-chat-modal">
            <div class="esim-chat-modal-overlay"></div>
            <div class="esim-chat-modal-content">
                <div id="esim-chat-container" class="esim-chat-floating">
                    <div id="esim-chat-widget">
                        <div class="esim-chat-header">
                            <h3 data-translate="title"><?php echo esc_html($translations['title']); ?></h3>
                            <button id="esim-chat-close" class="esim-chat-close-btn" aria-label="<?php echo esc_attr($translations['close_chat']); ?>"></button>
                        </div>
                        <div id="esim-chat-messages" class="esim-chat-messages"></div>
                        <div class="esim-chat-input-area">
                            <div class="esim-chat-input-wrapper">
                                <input type="text" id="esim-chat-input" data-translate-placeholder="input_placeholder" placeholder="<?php echo esc_attr($translations['input_placeholder']); ?>" />
                                <button id="esim-chat-send" class="esim-chat-send-btn" data-translate="send_button"><?php echo esc_html($translations['send_button']); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function handle_chat_request() {
        check_ajax_referer('esim_chat_nonce', 'nonce');
        
        $mode = get_option('esim_chat_mode', 'openai');
        
        if ($mode === 'external') {
            $this->handle_external_server_request();
        } else {
            $this->handle_openai_request();
        }
    }
    
    private function handle_external_server_request() {
        $api_url = get_option('esim_chat_api_url', 'http://localhost:3000');
        $message = sanitize_text_field($_POST['message'] ?? '');
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        
        $response = wp_remote_post($api_url . '/chat', array(
            'timeout' => 30,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'message' => $message,
                'history' => $history
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Server connection error'));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(array('message' => 'Invalid server response'));
        }
    }
    
    private function handle_openai_request() {
        $api_key = get_option('esim_chat_openai_key', '');
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'OpenAI API key is not configured. Please set it in the plugin settings.'));
            return;
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        $history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        
        // Получаем IP пользователя для определения страны
        $user_ip = $this->get_user_ip();
        
        // Формируем системный промпт
        $system_prompt = $this->get_system_prompt($user_ip);
        
        // Формируем сообщения для OpenAI
        $messages = array(
            array('role' => 'system', 'content' => $system_prompt)
        );
        
        // Добавляем историю
        if (is_array($history) && !empty($history)) {
            foreach ($history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = array(
                        'role' => sanitize_text_field($msg['role']),
                        'content' => sanitize_text_field($msg['content'])
                    );
                }
            }
        }
        
        // Добавляем текущее сообщение
        $messages[] = array('role' => 'user', 'content' => $message);
        
        // Get selected model
        $model = get_option('esim_chat_openai_model', 'gpt-4o-mini');
        
        // Отправляем запрос к OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7
            ))
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'OpenAI API connection error'));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            wp_send_json_success(array(
                'reply' => trim($data['choices'][0]['message']['content'])
            ));
        } else {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            wp_send_json_error(array('message' => 'OpenAI error: ' . $error_msg));
        }
    }
    
    private function get_user_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    private function get_system_prompt($user_ip) {
        $scenarios = get_option('esim_chat_response_scenarios', '');
        $response_length = get_option('esim_chat_response_length', 'brief');
        
        $prompt = "You are a friendly and professional eSIM consultant. " .
            "Your specialization: eSIM technologies, connection, installation, and troubleshooting.\n\n" .
            "CRITICALLY IMPORTANT RULES:\n" .
            "- Answer ONLY questions related to eSIM. If a question is about eSIM in any way, you MUST provide a helpful answer.\n" .
            "- APN (Access Point Name) questions are ALWAYS considered eSIM-related. APN configuration is essential for eSIM connectivity and network access.\n" .
            "- Questions about APN settings, APN for specific countries/operators, APN configuration for eSIM, APN troubleshooting - all are eSIM-related questions.\n" .
            "- IP route questions are ALWAYS considered eSIM-related. IP route configuration is essential for eSIM network routing and connectivity.\n" .
            "- Questions about IP route settings, IP route for specific countries/operators, IP route configuration for eSIM, IP route troubleshooting - all are eSIM-related questions.\n" .
            "- If a user asks a question that is NOT related to eSIM, politely decline and redirect them. " .
            "Say something like: \"I'm an eSIM consultant and can only help with eSIM-related questions. " .
            "How can I help you with eSIM?\" or \"I specialize in eSIM technology. Do you have any questions about eSIM?\"\n" .
            "- Stay focused on eSIM topics only. Do not answer general questions about technology, phones, or other topics unless they are directly related to eSIM.\n\n" .
            "MAIN TOPICS you should answer (this is NOT an exhaustive list - answer ANY eSIM-related question):\n" .
            "- Supported devices (iPhone, Samsung, Google Pixel, etc.)\n" .
            "- Operators and eSIM plans\n" .
            "- eSIM purchase and activation\n" .
            "- eSIM installation via QR code\n" .
            "- Transferring eSIM between devices\n" .
            "- eSIM activation and deactivation\n" .
            "- Configuration on iOS and Android\n" .
            "- Troubleshooting (activation errors, no network, APN issues, roaming)\n" .
            "- APN configuration and settings (APN is essential for eSIM network connectivity)\n" .
            "- APN for different countries and operators\n" .
            "- IP route configuration and settings (IP route is essential for eSIM network routing)\n" .
            "- IP route for different countries and operators\n" .
            "- Device and operator compatibility\n" .
            "- eSIM pricing, plans, and packages\n" .
            "- eSIM for travel and roaming\n" .
            "- eSIM vs physical SIM cards\n" .
            "- eSIM technical specifications\n" .
            "- eSIM for business use\n" .
            "- Multiple eSIM profiles on one device\n" .
            "- eSIM security and privacy\n" .
            "- eSIM for different countries and regions\n" .
            "- OPERATOR QUESTIONS: If the user asks about operators in any country, provide brief information about the main operators that support eSIM. Keep it concise - just the essential facts.\n" .
            "- ANY OTHER eSIM-RELATED QUESTIONS: Answer comprehensively and helpfully\n\n" .
            "COMMUNICATION STYLE:\n" .
            "- LANGUAGE: By default, respond in the same language the user writes in\n" .
            "- Support ALL languages of the world (400+ languages)\n" .
            "- LANGUAGE SWITCHING: If the user EXPLICITLY asks to switch languages (e.g., \"speak English\", \"switch to English\", \"говори по-английски\", \"speak Ukrainian\", \"English please\", \"по-русски\"), IMMEDIATELY switch to the requested language and continue the conversation in it\n" .
            "- After switching languages at the user's request, continue using the new language until the next explicit request to change language\n" .
            "- If the user asks to change language, confirm the change briefly (e.g., \"Sure, I'll speak English now\" or \"Of course, I'll speak Ukrainian now\") and immediately switch to the new language\n" .
            "- BE NATURAL: Communicate like a real person, not a robot\n" .
            "- RESPONSE LENGTH (CRITICALLY IMPORTANT!): " . ($response_length === 'brief' ? 
                "Answer EXTREMELY BRIEFLY. Maximum 1-2 sentences. Be concise, direct, and to the point. No unnecessary words or explanations. Get straight to the answer." : 
                "Answer CONCISELY. Maximum 2-3 sentences for simple questions, up to 1 short paragraph for complex ones. Be brief and direct. Avoid long explanations unless absolutely necessary.") . "\n" .
            "- Friendly tone, but without excessive formality\n" .
            "- IMPORTANT: Never mix languages in one response - use only one language at a time\n\n" .
            "RESPONSE FORMATTING (CRITICALLY IMPORTANT!):\n" .
            "- Use simple chat-friendly formatting - NO headers (##, ###), NO horizontal lines (---), NO blockquotes\n" .
            "- Use double line breaks (empty line) between paragraphs for better readability\n" .
            "- For lists ALWAYS use bullet points (- or •)\n" .
            "- For step-by-step instructions use numbered lists (1., 2., 3.)\n" .
            "- Highlight **bold text** for key information, important points, device names, operators\n" .
            "- Use *italic* for emphasis on individual words or phrases\n" .
            "- Use `code` for command names, settings, technical terms\n" .
            "- Use [link text](url) for links when needed\n" .
            "- Structure responses with clear paragraphs separated by empty lines\n" .
            "- Use lists to enumerate advantages, features, requirements, steps\n" .
            "- Break complex information into logical blocks with empty lines between them\n" .
            "- Highlight important warnings or advice in bold text\n" .
            "- Make responses visually appealing and easily scannable\n" .
            "- Example of good format:\n" .
            "  **Important point:** Main response text with explanation.\n" .
            "  \n" .
            "  - Point 1\n" .
            "  - Point 2\n" .
            "  - Point 3\n" .
            "  \n" .
            "  Additional information with `technical terms` and [links](url) when needed.";
        
        // Add custom response scenarios if they are set
        if (!empty($scenarios)) {
            $scenarios_text = strip_tags($scenarios); // Remove HTML tags
            $scenarios_text = trim($scenarios_text);
            if (!empty($scenarios_text)) {
                $prompt .= "\n\nADDITIONAL SCENARIOS AND INSTRUCTIONS:\n";
                $prompt .= "Follow these instructions when responding to relevant topics:\n";
                $prompt .= $scenarios_text . "\n";
                $prompt .= "Use these scenarios as a guide, but adapt responses to the user's specific situation.\n";
            }
        }
        
        // Add APN data if it is set
        $apn_data = get_option('esim_chat_apn_data', '');
        if (!empty($apn_data)) {
            $apn_text = strip_tags($apn_data); // Remove HTML tags
            $apn_text = trim($apn_text);
            if (!empty($apn_text)) {
                $prompt .= "\n\nAPN INFORMATION DATABASE:\n";
                $prompt .= "Use the following APN information when answering questions about APN settings:\n";
                $prompt .= $apn_text . "\n";
                $prompt .= "When a user asks about APN for a specific country or operator, check this database first and provide the exact APN name if available.\n";
            }
        }
        
        // Add IP route data if it is set
        $ip_route_data = get_option('esim_chat_ip_route_data', '');
        if (!empty($ip_route_data)) {
            $ip_route_text = strip_tags($ip_route_data); // Remove HTML tags
            $ip_route_text = trim($ip_route_text);
            if (!empty($ip_route_text)) {
                $prompt .= "\n\nIP ROUTE INFORMATION DATABASE:\n";
                $prompt .= "Use the following IP route information when answering questions about IP route settings:\n";
                $prompt .= $ip_route_text . "\n";
                $prompt .= "When a user asks about IP route for a specific country or operator, check this database first and provide the exact IP route if available.\n";
            }
        }
        
        // Add Q&A pairs if they are set
        $qa_data = get_option('esim_chat_qa_data', '');
        if (!empty($qa_data)) {
            $qa_text = strip_tags($qa_data); // Remove HTML tags
            $qa_text = trim($qa_text);
            if (!empty($qa_text)) {
                $prompt .= "\n\nQUESTION & ANSWER DATABASE:\n";
                $prompt .= "The following are predefined question and answer pairs. When a user asks a question that matches or is similar to any of these questions, use the corresponding answer as a reference:\n";
                $prompt .= $qa_text . "\n";
                $prompt .= "IMPORTANT: When a user's question matches or is very similar to a question in this database, prioritize using the provided answer. " .
                    "You can adapt the answer slightly to match the user's specific wording or context, but keep the core information from the database answer. " .
                    "If the user's question doesn't match any question in this database, answer normally based on your eSIM knowledge.\n";
            }
        }
        
        return $prompt;
    }
}

// Инициализация плагина
function esim_chat_init() {
    return ESIM_Chat::get_instance();
}

// Запускаем плагин
esim_chat_init();

