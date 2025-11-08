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
        add_action('wp_ajax_esim_chat_git_update', array($this, 'handle_git_update'));
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
        register_setting('esim_chat_settings', 'esim_chat_enabled');
        register_setting('esim_chat_settings', 'esim_chat_display_type');
        register_setting('esim_chat_settings', 'esim_chat_language');
        register_setting('esim_chat_settings', 'esim_chat_response_scenarios');
        register_setting('esim_chat_settings', 'esim_chat_response_length');
        
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
            
            <h2>Update from Git</h2>
            <p>Update the plugin from the Git repository. This will run <code>git pull</code> in the plugin directory.</p>
            <button type="button" id="esim-chat-git-update-btn" class="button button-secondary">
                <span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Update from Git
            </button>
            <span id="esim-chat-git-update-status" style="margin-left: 10px;"></span>
            
            <script>
            jQuery(document).ready(function($) {
                $('#esim-chat-git-update-btn').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#esim-chat-git-update-status');
                    
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align: middle; animation: spin 1s linear infinite;"></span> Updating...');
                    $status.html('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'esim_chat_git_update',
                            nonce: '<?php echo wp_create_nonce('esim_chat_git_update'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.html('<span style="color: #46b450;">✓ ' + $('<div>').text(response.data.message || 'Updated successfully!').html() + '</span>');
                                if (response.data.output) {
                                    $status.append('<pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; max-height: 300px; overflow-y: auto; font-size: 12px;">' + $('<div>').text(response.data.output).html() + '</pre>');
                                }
                            } else {
                                $status.html('<span style="color: #dc3232;">✗ ' + $('<div>').text(response.data.message || 'Update failed!').html() + '</span>');
                                if (response.data.output) {
                                    $status.append('<pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; max-height: 300px; overflow-y: auto; font-size: 12px; color: #dc3232;">' + $('<div>').text(response.data.output).html() + '</pre>');
                                }
                            }
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Update from Git');
                        },
                        error: function() {
                            $status.html('<span style="color: #dc3232;">✗ Error: Could not connect to server</span>');
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> Update from Git');
                        }
                    });
                });
            });
            </script>
            <style>
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            </style>
        </div>
        <?php
    }
    
    public function handle_git_update() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'esim_chat_git_update')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get plugin directory
        $plugin_dir = dirname(__FILE__);
        
        // Check if .git directory exists
        if (!is_dir($plugin_dir . '/.git')) {
            wp_send_json_error(array('message' => 'Git repository not found in plugin directory'));
            return;
        }
        
        // Check if git command is available
        $git_check = shell_exec('which git 2>&1');
        if (empty($git_check) || strpos($git_check, 'not found') !== false) {
            wp_send_json_error(array('message' => 'Git command not found. Please install Git on your server.'));
            return;
        }
        
        // Change to plugin directory and run git pull
        $command = 'cd ' . escapeshellarg($plugin_dir) . ' && git pull 2>&1';
        
        // Execute git pull
        $output = shell_exec($command);
        
        if ($output === null) {
            wp_send_json_error(array('message' => 'Failed to execute git pull command'));
            return;
        }
        
        // Check if update was successful
        $output_trimmed = trim($output);
        $is_success = (
            strpos($output_trimmed, 'Already up to date') !== false ||
            strpos($output_trimmed, 'Updating') !== false ||
            strpos($output_trimmed, 'Fast-forward') !== false ||
            strpos($output_trimmed, 'Merge made') !== false ||
            preg_match('/\d+ file[s]? changed/', $output_trimmed)
        );
        
        if ($is_success) {
            wp_send_json_success(array(
                'message' => 'Update completed successfully',
                'output' => esc_html($output_trimmed)
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Git pull completed but may have encountered issues',
                'output' => esc_html($output_trimmed)
            ));
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
                            <button id="esim-chat-close" class="esim-chat-close-btn" aria-label="<?php echo esc_attr($translations['close_chat']); ?>">×</button>
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
        
        // Отправляем запрос к OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
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
        
        return $prompt;
    }
}

// Инициализация плагина
function esim_chat_init() {
    return ESIM_Chat::get_instance();
}

// Запускаем плагин
esim_chat_init();

