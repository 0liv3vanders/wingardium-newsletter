<?php
/*
Plugin Name: Wingardium Newsletter
Plugin URI: https://github.com/0liv3vanders/
Description: Un plugin newsletter magique qui propulse vos emails, gère vos abonnés, envoie des potions SMTP, propose des templates ensorcelés avec aperçu en direct, et plus encore. Adieu la routine, bonjour l’enchantement !
Version: 1.1
Author: 0liv3vanders
Author URI: https://github.com/0liv3vanders/
License: GPL2
Text Domain: wingardium-newsletter
Update URI: https://github.com/0liv3vanders/wingardium-newsletter
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Empêcher l’accès direct
}

class WingardiumNewsletter {

    private static $instance = null;
    private $table_subscribers;
    private $table_logs;

    // On ne se sert plus d’une variable statique pour le message d’alerte,
    // on va gérer ça autrement avec des modales.
    // private static $subscribe_message = '';

    /**
     * Singleton
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_subscribers = $wpdb->prefix . 'wingardium_subscribers';
        $this->table_logs        = $wpdb->prefix . 'wingardium_newsletter_logs';

        // Hooks activation/désactivation
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));

        // Admin
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'settings_update_notice'));

        // Shortcode
        add_shortcode('wingardium_subscribe', array($this, 'shortcode_subscribe_form'));

        // Côté front
        add_action('init', array($this, 'handle_form_submission'));
        add_action('init', array($this, 'handle_unsubscribe_request'));

        // PHPMailer config
        add_action('phpmailer_init', array($this, 'configure_smtp'));

        // Gérer désinscription via l’admin
        add_action('admin_init', array($this, 'handle_admin_unsubscribe'));

        // Upload / Edition template
        add_action('admin_post_wingardium_upload_template', array($this, 'handle_template_upload'));
        add_action('admin_post_wingardium_edit_template', array($this, 'handle_template_edit'));

        // Détection GitHub Updater (alerte si non présent)
        add_action('admin_notices', array($this, 'maybe_show_github_updater_notice'));

        // AJAX (recherche dynamique) : abonné + templates
        add_action('wp_ajax_wingardium_search_subscribers', array($this, 'ajax_search_subscribers'));
        add_action('wp_ajax_wingardium_search_templates', array($this, 'ajax_search_templates'));

        // Charger un script JS (admin) pour la recherche dynamique
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));

        // ** SECTION NOUVELLE **
        // Pour injecter en front un JS/CSS minimal nécessaire aux modales
        add_action('wp_enqueue_scripts', array($this, 'frontend_modal_assets'));
    }

    /**
     * Activation : création/mise à jour des tables
     */
    public function activate_plugin() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $charset_collate = $wpdb->get_charset_collate();

        // Table abonnés
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_subscribers} (
            id INT NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            date_subscribed DATETIME NOT NULL,
            unsubscribe_token VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql1);

        // Vérifier la colonne unsubscribe_token
        $columns_sub = $wpdb->get_col("DESC {$this->table_subscribers}", 0);
        if (!in_array('unsubscribe_token', $columns_sub)) {
            $wpdb->query("ALTER TABLE {$this->table_subscribers} ADD `unsubscribe_token` VARCHAR(50) DEFAULT NULL");
        }

        // Table historique
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->table_logs} (
            id INT NOT NULL AUTO_INCREMENT,
            subject TEXT NOT NULL,
            message LONGTEXT NOT NULL,
            date_sent DATETIME NOT NULL,
            recipients_count INT DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql2);

        add_option('wingardium_newsletter_github_notice', true);
    }

    /**
     * Désactivation
     */
    public function deactivate_plugin() {
        // Optionnel : supprimer les tables
        // global $wpdb;
        // $wpdb->query("DROP TABLE IF EXISTS {$this->table_subscribers}");
        // $wpdb->query("DROP TABLE IF EXISTS {$this->table_logs}");
    }

    /**
     * Notice quand settings sont mis à jour
     */
    public function settings_update_notice() {
        if ( isset($_GET['settings-updated']) && $_GET['settings-updated'] ) {
            add_action('admin_notices', function(){
                echo '<div class="notice notice-success is-dismissible"><p>Les paramètres ont bien été modifiés.</p></div>';
            });
        }
    }

    /**
     * Alerte si GitHub Updater n’est pas actif
     */
    public function maybe_show_github_updater_notice() {
        if (!current_user_can('manage_options')) return;
        if (isset($_GET['page']) && $_GET['page'] === 'wingardium-newsletter') {
            include_once(ABSPATH.'wp-admin/includes/plugin.php');
            if (!is_plugin_active('github-updater/github-updater.php')) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>';
                echo '<strong>Wingardium Newsletter :</strong> ';
                echo 'Le plugin <em>GitHub Updater</em> n\'est pas détecté ou pas activé. ';
                echo 'Installez-le pour maintenir ce plugin à jour automatiquement depuis GitHub.';
                echo '</p>';
                echo '</div>';
            }
        }
    }

    /**
     * Enregistrement des réglages
     */
    public function register_settings() {

        /**
         * SECTION FORMULAIRE
         */
        add_settings_section(
            'wingardium_form_section',
            '',
            null,
            'wingardium_form_settings'
        );

        // Label on/off
        register_setting('wingardium_form_settings', 'wingardium_label_enabled');
        add_settings_field(
            'wingardium_label_enabled',
            'Afficher le label',
            array($this, 'field_label_enabled_cb'),
            'wingardium_form_settings',
            'wingardium_form_section'
        );

        // Texte label
        register_setting('wingardium_form_settings', 'wingardium_label_text');
        add_settings_field(
            'wingardium_label_text',
            'Texte du label',
            array($this, 'field_label_text_cb'),
            'wingardium_form_settings',
            'wingardium_form_section'
        );

        // Placeholder
        register_setting('wingardium_form_settings', 'wingardium_placeholder_text');
        add_settings_field(
            'wingardium_placeholder_text',
            'Placeholder',
            array($this, 'field_placeholder_text_cb'),
            'wingardium_form_settings',
            'wingardium_form_section'
        );

        // Texte bouton
        register_setting('wingardium_form_settings', 'wingardium_button_text');
        add_settings_field(
            'wingardium_button_text',
            'Texte du bouton',
            array($this, 'field_button_text_cb'),
            'wingardium_form_settings',
            'wingardium_form_section'
        );

        /**
         * SECTION SMTP
         */
        add_settings_section(
            'wingardium_smtp_section',
            '',
            null,
            'wingardium_smtp_settings'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_enabled');
        add_settings_field(
            'wingardium_smtp_enabled',
            'Activer SMTP',
            array($this, 'field_smtp_enabled_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_host');
        add_settings_field(
            'wingardium_smtp_host',
            'Hôte SMTP',
            array($this, 'field_smtp_host_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_port');
        add_settings_field(
            'wingardium_smtp_port',
            'Port SMTP',
            array($this, 'field_smtp_port_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_encryption');
        add_settings_field(
            'wingardium_smtp_encryption',
            'Chiffrement (ssl/tls/none)',
            array($this, 'field_smtp_encryption_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_username');
        add_settings_field(
            'wingardium_smtp_username',
            'Nom d’utilisateur SMTP',
            array($this, 'field_smtp_username_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        register_setting('wingardium_smtp_settings', 'wingardium_smtp_password');
        add_settings_field(
            'wingardium_smtp_password',
            'Mot de passe SMTP',
            array($this, 'field_smtp_password_cb'),
            'wingardium_smtp_settings',
            'wingardium_smtp_section'
        );

        /**
         * SECTION TEMPLATES (newsletter)
         */
        add_settings_section(
            'wingardium_email_templates_section',
            '',
            null,
            'wingardium_email_templates_settings'
        );

        // -> Sélection du template pour la newsletter
        register_setting('wingardium_email_templates_settings', 'wingardium_email_template');
        add_settings_field(
            'wingardium_email_template',
            'Template pour la Newsletter (sélection)',
            array($this, 'field_email_template_cb'),
            'wingardium_email_templates_settings',
            'wingardium_email_templates_section'
        );

        /**
         * SECTION PARAMÈTRES ENVOI (From + BCC)
         */
        add_settings_section(
            'wingardium_email_settings_section',
            '',
            null,
            'wingardium_email_settings'
        );

        register_setting('wingardium_email_settings', 'wingardium_from_name');
        add_settings_field(
            'wingardium_from_name',
            'Nom de l’expéditeur',
            array($this, 'field_from_name_cb'),
            'wingardium_email_settings',
            'wingardium_email_settings_section'
        );

        register_setting('wingardium_email_settings', 'wingardium_from_address');
        add_settings_field(
            'wingardium_from_address',
            'Adresse e-mail de l’expéditeur',
            array($this, 'field_from_address_cb'),
            'wingardium_email_settings',
            'wingardium_email_settings_section'
        );

        register_setting('wingardium_email_settings', 'wingardium_bcc_address');
        add_settings_field(
            'wingardium_bcc_address',
            'Adresse BCC',
            array($this, 'field_bcc_address_cb'),
            'wingardium_email_settings',
            'wingardium_email_settings_section'
        );

        /**
         * SECTION EMAILS AUTOMATIQUES : INSCRIPTION / DÉSINSCRIPTION
         */
        add_settings_section(
            'wingardium_auto_emails_section',
            'Emails automatiques',
            null,
            'wingardium_auto_emails_settings'
        );

        // -- Email d'inscription
        register_setting('wingardium_auto_emails_settings', 'wingardium_subscribe_subject');
        add_settings_field(
            'wingardium_subscribe_subject',
            'Sujet (email inscription)',
            array($this, 'field_subscribe_subject_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        register_setting('wingardium_auto_emails_settings', 'wingardium_subscribe_message');
        add_settings_field(
            'wingardium_subscribe_message',
            'Message (email inscription)',
            array($this, 'field_subscribe_message_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        register_setting('wingardium_auto_emails_settings', 'wingardium_subscribe_template');
        add_settings_field(
            'wingardium_subscribe_template',
            'Template (email inscription)',
            array($this, 'field_subscribe_template_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        // -- Email de désinscription
        register_setting('wingardium_auto_emails_settings', 'wingardium_unsubscribe_subject');
        add_settings_field(
            'wingardium_unsubscribe_subject',
            'Sujet (email désinscription)',
            array($this, 'field_unsubscribe_subject_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        register_setting('wingardium_auto_emails_settings', 'wingardium_unsubscribe_message');
        add_settings_field(
            'wingardium_unsubscribe_message',
            'Message (email désinscription)',
            array($this, 'field_unsubscribe_message_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        register_setting('wingardium_auto_emails_settings', 'wingardium_unsubscribe_template');
        add_settings_field(
            'wingardium_unsubscribe_template',
            'Template (email désinscription)',
            array($this, 'field_unsubscribe_template_cb'),
            'wingardium_auto_emails_settings',
            'wingardium_auto_emails_section'
        );

        // ** SECTION NOUVELLE : PARAMÈTRES DES MODALES (SUBSCRIBE / UNSUBSCRIBE) **
        add_settings_section(
            'wingardium_modals_section',
            'Modales d’inscription / désinscription',
            null,
            'wingardium_modals_settings'
        );

        // Titre modale inscription
        register_setting('wingardium_modals_settings', 'wingardium_modal_subscribe_title');
        add_settings_field(
            'wingardium_modal_subscribe_title',
            'Titre – Modale succès inscription',
            array($this, 'field_modal_subscribe_title_cb'),
            'wingardium_modals_settings',
            'wingardium_modals_section'
        );

        // Contenu modale inscription
        register_setting('wingardium_modals_settings', 'wingardium_modal_subscribe_content');
        add_settings_field(
            'wingardium_modal_subscribe_content',
            'Texte – Modale succès inscription',
            array($this, 'field_modal_subscribe_content_cb'),
            'wingardium_modals_settings',
            'wingardium_modals_section'
        );

        // Titre modale désinscription
        register_setting('wingardium_modals_settings', 'wingardium_modal_unsubscribe_title');
        add_settings_field(
            'wingardium_modal_unsubscribe_title',
            'Titre – Modale succès désinscription',
            array($this, 'field_modal_unsubscribe_title_cb'),
            'wingardium_modals_settings',
            'wingardium_modals_section'
        );

        // Contenu modale désinscription
        register_setting('wingardium_modals_settings', 'wingardium_modal_unsubscribe_content');
        add_settings_field(
            'wingardium_modal_unsubscribe_content',
            'Texte – Modale succès désinscription',
            array($this, 'field_modal_unsubscribe_content_cb'),
            'wingardium_modals_settings',
            'wingardium_modals_section'
        );
    }

    /* ---------------------------------------------------------------------
       FORMULAIRE - FIELDS
    ---------------------------------------------------------------------- */
    public function field_label_enabled_cb() {
        $val = get_option('wingardium_label_enabled','1');
        ?>
        <input type="checkbox" name="wingardium_label_enabled" value="1" <?php checked($val, '1'); ?>>
        <span>Afficher un label au-dessus du champ email</span>
        <?php
    }
    public function field_label_text_cb() {
        $val = get_option('wingardium_label_text','Entrez votre email :');
        ?>
        <input type="text" name="wingardium_label_text" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_placeholder_text_cb() {
        $val = get_option('wingardium_placeholder_text','Votre email');
        ?>
        <input type="text" name="wingardium_placeholder_text" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_button_text_cb() {
        $val = get_option('wingardium_button_text',"M'inscrire");
        ?>
        <input type="text" name="wingardium_button_text" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }

    /* ---------------------------------------------------------------------
       SMTP - FIELDS
    ---------------------------------------------------------------------- */
    public function field_smtp_enabled_cb() {
        $val = get_option('wingardium_smtp_enabled','0');
        ?>
        <input type="checkbox" id="wingardium_smtp_enabled" name="wingardium_smtp_enabled" value="1" <?php checked($val, '1'); ?>>
        <span>Utiliser un serveur SMTP externe</span>
        <?php
    }
    public function field_smtp_host_cb() {
        $val = get_option('wingardium_smtp_host','');
        ?>
        <input type="text" id="wingardium_smtp_host" name="wingardium_smtp_host" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <p class="description">Ex: smtp.gmail.com, smtp.sendgrid.net, etc.</p>
        <?php
    }
    public function field_smtp_port_cb() {
        $val = get_option('wingardium_smtp_port','');
        ?>
        <input type="text" id="wingardium_smtp_port" name="wingardium_smtp_port" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <p class="description">Ex: 465 (SSL), 587 (TLS)...</p>
        <?php
    }
    public function field_smtp_encryption_cb() {
        $val = get_option('wingardium_smtp_encryption','none');
        ?>
        <select id="wingardium_smtp_encryption" name="wingardium_smtp_encryption">
            <option value="none" <?php selected($val,'none'); ?>>Aucun</option>
            <option value="ssl" <?php selected($val,'ssl'); ?>>SSL</option>
            <option value="tls" <?php selected($val,'tls'); ?>>TLS</option>
        </select>
        <?php
    }
    public function field_smtp_username_cb() {
        $val = get_option('wingardium_smtp_username','');
        ?>
        <input type="text" id="wingardium_smtp_username" name="wingardium_smtp_username" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_smtp_password_cb() {
        $val = get_option('wingardium_smtp_password','');
        ?>
        <input type="password" id="wingardium_smtp_password" name="wingardium_smtp_password" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }

    /* ---------------------------------------------------------------------
       TEMPLATE NEWSLETTER - FIELDS
    ---------------------------------------------------------------------- */
    public function field_email_template_cb() {
        $selected = get_option('wingardium_email_template','modern');
        $templates_found = $this->find_templates_in_folder();

        if(empty($templates_found)){
            echo "<p>Aucun fichier <code>template_*.html</code> trouvé dans <code>/templates</code>.</p>";
            return;
        }
        ?>
        <select name="wingardium_email_template">
            <?php foreach($templates_found as $slug): ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug,$selected); ?>>
                    <?php echo ucfirst($slug); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            Sélectionnez le template principal pour l’envoi de vos newsletters.
        </p>
        <?php
    }

    /* ---------------------------------------------------------------------
       PARAM. ENVOI - FIELDS
    ---------------------------------------------------------------------- */
    public function field_from_name_cb() {
        $val = get_option('wingardium_from_name', get_bloginfo('name'));
        ?>
        <input type="text" name="wingardium_from_name" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_from_address_cb() {
        $val = get_option('wingardium_from_address', get_bloginfo('admin_email'));
        ?>
        <input type="email" name="wingardium_from_address" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_bcc_address_cb() {
        $val = get_option('wingardium_bcc_address','');
        ?>
        <input type="email" name="wingardium_bcc_address" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <p class="description">Copie cachée pour chaque email envoyé (optionnel).</p>
        <?php
    }

    /* ---------------------------------------------------------------------
       EMAILS AUTO - FIELDS
    ---------------------------------------------------------------------- */
    // -- Inscription
    public function field_subscribe_subject_cb() {
        $val = get_option('wingardium_subscribe_subject', 'Bienvenue dans notre newsletter');
        ?>
        <input type="text" name="wingardium_subscribe_subject" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_subscribe_message_cb() {
        $val = get_option('wingardium_subscribe_message', "Bonjour,<br>Merci de vous être inscrit(e) !");
        ?>
        <textarea name="wingardium_subscribe_message" rows="5" class="large-text"><?php echo esc_textarea($val); ?></textarea>
        <p class="description">
            Vous pouvez insérer <code>{UNSUBSCRIBE_LINK}</code> pour y placer un lien de désinscription si nécessaire.
        </p>
        <?php
    }
    public function field_subscribe_template_cb() {
        $val = get_option('wingardium_subscribe_template','modern');
        $templates_found = $this->find_templates_in_folder();
        ?>
        <select name="wingardium_subscribe_template">
            <?php foreach($templates_found as $slug): ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug,$val); ?>>
                    <?php echo ucfirst($slug); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    // -- Désinscription
    public function field_unsubscribe_subject_cb() {
        $val = get_option('wingardium_unsubscribe_subject', 'Désinscription effectuée');
        ?>
        <input type="text" name="wingardium_unsubscribe_subject" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_unsubscribe_message_cb() {
        $val = get_option('wingardium_unsubscribe_message', "Vous venez de vous désinscrire de notre newsletter.<br>À bientôt&nbsp;!");
        ?>
        <textarea name="wingardium_unsubscribe_message" rows="5" class="large-text"><?php echo esc_textarea($val); ?></textarea>
        <p class="description">
            Vous pouvez insérer <code>{UNSUBSCRIBE_LINK}</code> pour y placer un lien de désinscription si nécessaire.
        </p>
        <?php
    }
    public function field_unsubscribe_template_cb() {
        $val = get_option('wingardium_unsubscribe_template','modern');
        $templates_found = $this->find_templates_in_folder();
        ?>
        <select name="wingardium_unsubscribe_template">
            <?php foreach($templates_found as $slug): ?>
                <option value="<?php echo esc_attr($slug); ?>" <?php selected($slug,$val); ?>>
                    <?php echo ucfirst($slug); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /* ---------------------------------------------------------------------
       SECTION NOUVELLE : MODALES - FIELDS
    ---------------------------------------------------------------------- */
    public function field_modal_subscribe_title_cb() {
        $val = get_option('wingardium_modal_subscribe_title','Inscription réussie');
        ?>
        <input type="text" name="wingardium_modal_subscribe_title" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_modal_subscribe_content_cb() {
        $val = get_option('wingardium_modal_subscribe_content','Merci, vous êtes bien inscrit(e) à notre newsletter !');
        ?>
        <textarea name="wingardium_modal_subscribe_content" rows="3" class="large-text"><?php echo esc_textarea($val); ?></textarea>
        <?php
    }
    public function field_modal_unsubscribe_title_cb() {
        $val = get_option('wingardium_modal_unsubscribe_title','Désinscription confirmée');
        ?>
        <input type="text" name="wingardium_modal_unsubscribe_title" value="<?php echo esc_attr($val); ?>" class="regular-text">
        <?php
    }
    public function field_modal_unsubscribe_content_cb() {
        $val = get_option('wingardium_modal_unsubscribe_content','Vous êtes bien désinscrit(e) de la newsletter.');
        ?>
        <textarea name="wingardium_modal_unsubscribe_content" rows="3" class="large-text"><?php echo esc_textarea($val); ?></textarea>
        <?php
    }

    /* ---------------------------------------------------------------------
       TROUVER TEMPLATES
    ---------------------------------------------------------------------- */
    private function find_templates_in_folder() {
        $template_dir = plugin_dir_path(__FILE__).'templates/';
        $templates_found = array();
        if(is_dir($template_dir)){
            $files = glob($template_dir.'template_*.html');
            foreach($files as $file){
                $base = basename($file,'.html'); // ex: template_modern
                $slug = str_replace('template_','',$base);
                $templates_found[] = $slug;
            }
        }
        return $templates_found;
    }

    /* ---------------------------------------------------------------------
       MENU ADMIN
    ---------------------------------------------------------------------- */
    public function add_admin_menu() {
        add_menu_page(
            'Wingardium Newsletter',
            'Wingardium Newsletter',
            'manage_options',
            'wingardium-newsletter',
            array($this, 'admin_page'),
            'dashicons-email-alt',
            26
        );
    }

    /**
     * Chargement d’un script JS custom en admin (pour la recherche AJAX)
     */
    public function admin_assets($hook) {
        if($hook === 'toplevel_page_wingardium-newsletter'){
            // On n’injecte que sur notre page d’options
            wp_enqueue_script(
                'wingardium-admin-search',
                plugin_dir_url(__FILE__).'wingardium-admin-search.js',
                array('jquery'),
                '1.0',
                true
            );
            // On passe l’URL d’ajax et un nonce
            wp_localize_script('wingardium-admin-search','WingardiumAjax',array(
                'ajaxUrl'=>admin_url('admin-ajax.php'),
                'nonce'=>wp_create_nonce('wingardium_ajax_search')
            ));
        }
    }

    // ** SECTION NOUVELLE : Enqueue de CSS/JS minimal en front pour afficher les modales **
    public function frontend_modal_assets() {
        // Un petit CSS simple pour la modale
        wp_enqueue_style('wingardium-modal-css', plugin_dir_url(__FILE__).'wingardium-modal.css', array(), '1.0');

        // JS minimal qui gère l’ouverture/fermeture
        wp_enqueue_script('wingardium-modal-js', plugin_dir_url(__FILE__).'wingardium-modal.js', array('jquery'), '1.0', true);
    }

    /**
     * Page admin (onglets)
     */
    public function admin_page() {
        if(!current_user_can('manage_options')) return;
        global $wpdb;

        // Envoi newsletter
        if( isset($_POST['wingardium_send_newsletter'])
            && check_admin_referer('wingardium_send_newsletter_action','wingardium_send_newsletter_nonce') ){

            $subject = sanitize_text_field($_POST['wingardium_subject']);
            $message = wp_kses_post($_POST['wingardium_message']);
            $this->send_newsletter($subject,$message);

            // Alerte de succès
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>La newsletter a bien été envoyée&nbsp;!</p></div>';
            });
        }

        // Historique
        $logs = $wpdb->get_results("SELECT * FROM {$this->table_logs} ORDER BY date_sent DESC");
        ?>
        <div class="wrap">
            <h1>Wingardium Newsletter</h1>

            <p style="max-width:800px;">
            <ol style="margin-left:20px;">
                <li>Insérez le shortcode <code>[wingardium_subscribe]</code> dans une page ou un article.</li>
                <li>Paramétrez (Formulaire, SMTP, Templates, etc.) ci-dessous.</li>
                <li>Envoyez une newsletter à tous vos abonnés (onglet « Abonnés & Newsletter »).</li>
                <li>Consultez l’historique (onglet « Historique »).</li>
            </ol>
            </p>

            <h2 class="nav-tab-wrapper">
                <a href="#wingardium-tab-liste" class="nav-tab nav-tab-active">Abonnés & Newsletter</a>
                <a href="#wingardium-tab-formulaire" class="nav-tab">Formulaire</a>
                <a href="#wingardium-tab-smtp" class="nav-tab">SMTP</a>
                <a href="#wingardium-tab-templates" class="nav-tab">Templates</a>
                <a href="#wingardium-tab-emailsettings" class="nav-tab">Param. d’Envoi</a>
                <a href="#wingardium-tab-autoemails" class="nav-tab">Emails Inscr./Désinscr.</a>
                <a href="#wingardium-tab-modals" class="nav-tab">Modales</a>
                <a href="#wingardium-tab-historique" class="nav-tab">Historique</a>
            </h2>

            <!-- Onglet 1 : Abonnés / Newsletter -->
            <div id="wingardium-tab-liste" class="wingardium-tab-content" style="display:block;">
                <h2>Envoyer une Newsletter</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('wingardium_send_newsletter_action','wingardium_send_newsletter_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="wingardium_subject">Sujet</label></th>
                            <td><input type="text" name="wingardium_subject" id="wingardium_subject" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="wingardium_message">Message (HTML)</label></th>
                            <td>
                                <?php
                                wp_editor(
                                    '',
                                    'wingardium_message',
                                    array(
                                        'textarea_name' => 'wingardium_message',
                                        'media_buttons' => false,
                                        'teeny'         => true,
                                        'textarea_rows' => 8
                                    )
                                );
                                ?>
                                <p class="description">
                                    Vous pouvez insérer <code>{UNSUBSCRIBE_LINK}</code> si vous souhaitez placer manuellement le lien de désinscription.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p><input type="submit" name="wingardium_send_newsletter" class="button button-primary" value="Envoyer la Newsletter"></p>
                </form>

                <hr>
                <h2>Liste des abonnés (recherche AJAX)</h2>
                <div style="margin:10px 0;">
                    <input type="text" id="wingardium-subscribers-search" placeholder="Rechercher un email..." style="width:300px;">
                    <span class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
                </div>

                <div id="wingardium-subscribers-list">
                    <!-- Le tableau sera chargé en AJAX directement -->
                </div>
            </div>

            <!-- Onglet 2 : Formulaire -->
            <div id="wingardium-tab-formulaire" class="wingardium-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_form_settings');
                    do_settings_sections('wingardium_form_settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- Onglet 3 : SMTP -->
            <div id="wingardium-tab-smtp" class="wingardium-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_smtp_settings');
                    do_settings_sections('wingardium_smtp_settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- Onglet 4 : Templates -->
            <div id="wingardium-tab-templates" class="wingardium-tab-content" style="display:none; margin-top:20px;">

                <!-- Formulaire "sélection du template pour la newsletter" -->
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_email_templates_settings');
                    do_settings_sections('wingardium_email_templates_settings');
                    submit_button();
                    ?>
                </form>

                <!-- Formulaire d’upload d’un nouveau template -->
                <hr>
                <h3>Ajouter un nouveau template</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="wingardium_upload_template">
                    <?php wp_nonce_field('wingardium_upload_template_action','wingardium_upload_template_nonce'); ?>
                    <p>
                        <input type="file" name="template_file" accept=".html" required>
                        <br><small>Le fichier doit se nommer <code>template_nom.html</code>.</small>
                    </p>
                    <p><input type="submit" class="button button-primary" value="Téléverser"></p>
                </form>

                <hr>
                <h3>Liste des templates (recherche AJAX)</h3>
                <div style="margin:10px 0;">
                    <input type="text" id="wingardium-templates-search" placeholder="Rechercher un template..." style="width:300px;">
                    <span class="spinner" style="float:none;vertical-align:middle;display:none;"></span>
                </div>

                <div id="wingardium-templates-list">
                    <!-- La liste + iframes seront chargés en AJAX -->
                </div>
            </div>

            <!-- Onglet 5 : Paramètres d’Envoi -->
            <div id="wingardium-tab-emailsettings" class="wingardium-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_email_settings');
                    do_settings_sections('wingardium_email_settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- Onglet 6 : Emails auto -->
            <div id="wingardium-tab-autoemails" class="wingardium-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_auto_emails_settings');
                    do_settings_sections('wingardium_auto_emails_settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- ** Onglet 7 : Modales (NOUVEAU) -->
            <div id="wingardium-tab-modals" class="wingardium-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wingardium_modals_settings');
                    do_settings_sections('wingardium_modals_settings');
                    submit_button();
                    ?>
                </form>
            </div>

            <!-- Onglet 8 : Historique -->
            <div id="wingardium-tab-historique" class="wingardium-tab-content" style="display:none;">
                <h2>Historique des Newsletters</h2>
                <table class="widefat">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sujet</th>
                        <th>Nb. Destinataires</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if(!empty($logs)): ?>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->date_sent); ?></td>
                                <td><?php echo esc_html($log->subject); ?></td>
                                <td><?php echo intval($log->recipients_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">Aucun envoi enregistré pour le moment.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            (function(){
                const tabs = document.querySelectorAll('.nav-tab');
                const contents = document.querySelectorAll('.wingardium-tab-content');

                // Gestion onglets
                tabs.forEach(tab => {
                    tab.addEventListener('click', function(e){
                        e.preventDefault();
                        tabs.forEach(t => t.classList.remove('nav-tab-active'));
                        contents.forEach(c => c.style.display = 'none');
                        this.classList.add('nav-tab-active');
                        const target = this.getAttribute('href');
                        if(document.querySelector(target)){
                            document.querySelector(target).style.display='block';
                        }
                    });
                });

                // Petit hack pour focus l’onglet "templates" si param ?tab=templates
                const urlParams = new URLSearchParams(window.location.search);
                if(urlParams.get('tab') === 'templates'){
                    document.querySelector('.nav-tab[href="#wingardium-tab-templates"]').click();
                }
            })();
        </script>
        <?php
    }

    /* ---------------------------------------------------------------------
       AJAX : Recherche "subscribers" (abonnés)
    ---------------------------------------------------------------------- */
    public function ajax_search_subscribers() {
        check_ajax_referer('wingardium_ajax_search','security');
        if(!current_user_can('manage_options')){
            wp_send_json_error("Non autorisé");
        }

        global $wpdb;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $where = '';
        if(!empty($search)){
            $where = $wpdb->prepare("WHERE email LIKE %s", '%'.$search.'%');
        }
        $subscribers = $wpdb->get_results("SELECT * FROM {$this->table_subscribers} {$where} ORDER BY date_subscribed DESC");

        ob_start();
        ?>
        <table class="widefat">
            <thead>
            <tr>
                <th>Email</th>
                <th>Date d'abonnement</th>
                <th>Token</th>
                <th width="120">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if(!empty($subscribers)): ?>
                <?php foreach($subscribers as $sub): ?>
                    <tr>
                        <td><?php echo esc_html($sub->email); ?></td>
                        <td><?php echo esc_html($sub->date_subscribed); ?></td>
                        <td><?php echo esc_html($sub->unsubscribe_token); ?></td>
                        <td>
                            <?php
                            $nonce_url = wp_nonce_url(
                                admin_url('admin.php?page=wingardium-newsletter&action=admin_unsub&subscriber_id='.$sub->id),
                                'wingardium_admin_unsub_'.$sub->id
                            );
                            ?>
                            <a class="button" style="background:#c00;color:#fff;"
                               href="<?php echo esc_url($nonce_url); ?>"
                               onclick="return confirm('Confirmer la désinscription de cet abonné ?');"
                            >Désinscrire</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">Aucun abonné trouvé.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    /* ---------------------------------------------------------------------
       AJAX : Recherche "templates"
    ---------------------------------------------------------------------- */
    public function ajax_search_templates() {
        check_ajax_referer('wingardium_ajax_search','security');
        if(!current_user_can('manage_options')){
            wp_send_json_error("Non autorisé");
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        ob_start();
        $this->show_templates_preview($search);
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    /* ---------------------------------------------------------------------
       AFFICHAGE + IFRAME + FORM EDIT : TEMPLATES
    ---------------------------------------------------------------------- */
    private function show_templates_preview($search = '') {
        $templates = $this->find_templates_in_folder();
        if(empty($templates)){
            echo "<p>Aucun template détecté.</p>";
            return;
        }

        // Filtrer par recherche
        if(!empty($search)){
            $templates = array_filter($templates, function($slug) use ($search){
                return (stripos($slug, $search) !== false);
            });
        }

        if(empty($templates)){
            echo "<p>Aucun template ne correspond à votre recherche.</p>";
            return;
        }

        $template_dir = plugin_dir_path(__FILE__).'templates/';
        foreach($templates as $slug){
            $file = $template_dir."template_{$slug}.html";
            if(!file_exists($file)) continue;
            $content = file_get_contents($file);

            $preview_code = str_replace(
                array('{MESSAGE}','{UNSUBSCRIBE_LINK}'),
                array('<strong>Exemple de message</strong>', '<a href="#">lien de désinscription</a>'),
                $content
            );
            $base64 = base64_encode($preview_code);
            $src = "data:text/html;base64,".$base64;
            ?>
            <div style="border:1px solid #ccc; padding:10px; margin-top:20px;">
                <h4>Fichier : <code>template_<?php echo esc_html($slug); ?>.html</code></h4>
                <div style="margin:10px 0;">
                    <iframe style="width:100%;height:200px;border:1px solid #ddd;" src="<?php echo esc_attr($src); ?>"></iframe>
                </div>

                <!-- Éditer -->
                <button type="button" class="button" onclick="document.getElementById('edit-<?php echo esc_attr($slug); ?>').style.display='block';">
                    Éditer ce template
                </button>
                <div id="edit-<?php echo esc_attr($slug); ?>" style="display:none; margin-top:10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="wingardium_edit_template">
                        <?php wp_nonce_field('wingardium_edit_template_action','wingardium_edit_template_nonce'); ?>
                        <input type="hidden" name="template_slug" value="<?php echo esc_attr($slug); ?>">
                        <textarea name="template_content" rows="10" style="width:100%;"><?php echo esc_textarea($content); ?></textarea>
                        <p>
                            <input type="submit" class="button button-primary" value="Enregistrer les modifications">
                        </p>
                    </form>
                </div>
            </div>
            <?php
        }
    }

    /* ---------------------------------------------------------------------
       GESTION UPLOAD + EDIT TEMPLATES
    ---------------------------------------------------------------------- */
    public function handle_template_upload() {
        if(!current_user_can('manage_options')) wp_die('Non autorisé');
        check_admin_referer('wingardium_upload_template_action','wingardium_upload_template_nonce');

        if(isset($_FILES['template_file']) && $_FILES['template_file']['error'] === 0){
            $file = $_FILES['template_file'];
            $tmp_name = $file['tmp_name'];
            $name = $file['name'];
            // Vérifier extension .html
            if(strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'html'){
                wp_die("Le fichier doit être un .html");
            }

            $dest_dir = plugin_dir_path(__FILE__).'templates/';
            if(!is_dir($dest_dir)){
                wp_mkdir_p($dest_dir);
            }
            $dest_path = $dest_dir.$name;
            if(move_uploaded_file($tmp_name, $dest_path)){
                wp_redirect(admin_url('admin.php?page=wingardium-newsletter&tab=templates&upload_success=1'));
                exit;
            } else {
                wp_die("Échec du téléversement. Vérifiez les permissions du dossier /templates");
            }
        }
        wp_redirect(admin_url('admin.php?page=wingardium-newsletter&tab=templates'));
        exit;
    }

    public function handle_template_edit() {
        if(!current_user_can('manage_options')) wp_die('Non autorisé');
        check_admin_referer('wingardium_edit_template_action','wingardium_edit_template_nonce');

        if(isset($_POST['template_slug'], $_POST['template_content'])){
            $slug = sanitize_text_field($_POST['template_slug']);
            $new_content = wp_unslash($_POST['template_content']);

            $file_path = plugin_dir_path(__FILE__).'templates/template_'.$slug.'.html';
            if(file_exists($file_path)){
                if(is_writable($file_path)){
                    file_put_contents($file_path, $new_content);
                } else {
                    wp_die("Le fichier n’est pas accessible en écriture. Vérifiez les permissions.");
                }
            }
        }
        wp_redirect(admin_url('admin.php?page=wingardium-newsletter&tab=templates&edit_success=1'));
        exit;
    }

    /* ---------------------------------------------------------------------
       ABONNÉS - GESTION
    ---------------------------------------------------------------------- */
    public function handle_form_submission() {
        if(isset($_POST['wingardium_subscribe_submit'])){
            if( isset($_POST['wingardium_subscribe_nonce'])
                && wp_verify_nonce($_POST['wingardium_subscribe_nonce'],'wingardium_subscribe_action') ){

                $email = sanitize_email($_POST['wingardium_email']);
                if(is_email($email)){
                    $this->subscribe_email($email);
                    // On va ajouter un paramètre pour déclencher la modale d’inscription
                    wp_redirect(add_query_arg('wingardium_subscribed','1', remove_query_arg('wingardium_unsubscribed')));
                    exit;
                } else {
                    // En cas d’erreur on peut faire un autre param. (ex: wingardium_subscribe_error=1)
                    wp_redirect(add_query_arg('wingardium_subscribe_error','1'));
                    exit;
                }
            }
        }
    }

    private function subscribe_email($email) {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_subscribers} WHERE email = %s",
            $email
        ));
        if($exists){
            // L’utilisateur était déjà abonné
            return;
        }
        $token = wp_generate_password(20, false, false);
        $res = $wpdb->insert(
            $this->table_subscribers,
            array(
                'email' => $email,
                'date_subscribed' => current_time('mysql'),
                'unsubscribe_token'=> $token
            ),
            array('%s','%s','%s')
        );
        if($res!==false){
            // Envoi email d’inscription
            $this->send_custom_email(
                $email,
                get_option('wingardium_subscribe_subject', 'Bienvenue'),
                get_option('wingardium_subscribe_message', 'Bonjour, merci de vous être inscrit(e) !'),
                get_option('wingardium_subscribe_template','modern'),
                array(
                    'UNSUBSCRIBE_LINK' => $this->generate_unsubscribe_link($token),
                )
            );
        }
    }

    public function handle_unsubscribe_request() {
        if(isset($_GET['wingardium_unsubscribe'])){
            $token = sanitize_text_field($_GET['wingardium_unsubscribe']);
            if(!empty($token)){
                $this->unsubscribe_by_token($token);
                // Après désinscription, on redirige pour afficher la modale
                wp_redirect(add_query_arg('wingardium_unsubscribed','1', remove_query_arg('wingardium_subscribed')));
                exit;
            }
        }
    }

    public function handle_admin_unsubscribe() {
        if(!current_user_can('manage_options')) return;
        if(isset($_GET['action']) && $_GET['action']=='admin_unsub' && isset($_GET['subscriber_id'])){
            $subscriber_id = intval($_GET['subscriber_id']);
            $nonce_action  = 'wingardium_admin_unsub_'.$subscriber_id;
            if(!wp_verify_nonce($_REQUEST['_wpnonce'], $nonce_action)){
                return;
            }
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_subscribers} WHERE id=%d",$subscriber_id));
            if($row){
                $this->unsubscribe_by_token($row->unsubscribe_token);
                add_action('admin_notices', function(){
                    echo '<div class="notice notice-success is-dismissible"><p>L’abonné a été désinscrit.</p></div>';
                });
            }
            wp_redirect(admin_url('admin.php?page=wingardium-newsletter'));
            exit;
        }
    }

    private function unsubscribe_by_token($token) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_subscribers} WHERE unsubscribe_token = %s",$token));
        $wpdb->delete($this->table_subscribers,array('unsubscribe_token'=>$token),array('%s'));

        if($row && !empty($row->email)) {
            $this->send_custom_email(
                $row->email,
                get_option('wingardium_unsubscribe_subject','Désinscription confirmée'),
                get_option('wingardium_unsubscribe_message','Vous venez de vous désinscrire.'),
                get_option('wingardium_unsubscribe_template','modern'),
                array('UNSUBSCRIBE_LINK' => $this->generate_unsubscribe_link($token))
            );
        }
    }

    /* ---------------------------------------------------------------------
       ENVOI NEWSLETTER
    ---------------------------------------------------------------------- */
    private function send_newsletter($subject,$raw_message) {
        global $wpdb;
        $subscribers = $wpdb->get_results("SELECT email, unsubscribe_token FROM {$this->table_subscribers}");
        $count = count($subscribers);

        $template_slug = get_option('wingardium_email_template','modern');
        foreach($subscribers as $sub){
            $unsubscribe_link = $this->generate_unsubscribe_link($sub->unsubscribe_token);
            $this->send_custom_email(
                $sub->email,
                $subject,
                $raw_message,
                $template_slug,
                array(
                    'UNSUBSCRIBE_LINK' => $unsubscribe_link,
                )
            );
        }

        $wpdb->insert(
            $this->table_logs,
            array(
                'subject'          => $subject,
                'message'          => $raw_message,
                'date_sent'        => current_time('mysql'),
                'recipients_count' => $count
            ),
            array('%s','%s','%s','%d')
        );
    }

    private function generate_unsubscribe_link($token) {
        return add_query_arg(
            array('wingardium_unsubscribe'=>$token),
            home_url()
        );
    }

    private function send_custom_email($to, $subject, $message, $template_slug, $placeholders = array()) {
        $final_body = $this->build_email_body($template_slug, $message, $placeholders);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $result = wp_mail($to, $subject, $final_body, $headers);
        if(!$result){
            error_log("WingardiumNewsletter: wp_mail() a échoué pour $to, sujet=$subject");
        }
    }

    private function build_email_body($template_slug, $content, $placeholders = array()) {
        $template_file = plugin_dir_path(__FILE__)."templates/template_{$template_slug}.html";
        if(!file_exists($template_file)){
            $template_file = plugin_dir_path(__FILE__)."templates/template_modern.html";
        }
        $template_body = file_get_contents($template_file);
        $final_body = str_replace('{MESSAGE}', $content, $template_body);
        foreach($placeholders as $key => $val){
            $final_body = str_replace('{'.$key.'}', $val, $final_body);
        }
        return $final_body;
    }

    /* ---------------------------------------------------------------------
       SHORTCODE
    ---------------------------------------------------------------------- */
    public function shortcode_subscribe_form() {
        $label_enabled    = get_option('wingardium_label_enabled','1');
        $label_text       = get_option('wingardium_label_text','Entrez votre email :');
        $placeholder_text = get_option('wingardium_placeholder_text','Votre email');
        $button_text      = get_option('wingardium_button_text',"M'inscrire");

        ob_start();
        ?>
        <div class="wingardium-subscribe-form-wrapper">
            <form action="" method="POST">
                <?php wp_nonce_field('wingardium_subscribe_action','wingardium_subscribe_nonce'); ?>

                <?php if($label_enabled==='1'): ?>
                    <label for="wingardium_email"><?php echo esc_html($label_text); ?></label><br>
                <?php endif; ?>

                <input
                    type="email"
                    name="wingardium_email"
                    id="wingardium_email"
                    placeholder="<?php echo esc_attr($placeholder_text); ?>"
                    required
                >
                <br><br>
                <input type="submit" name="wingardium_subscribe_submit" value="<?php echo esc_attr($button_text); ?>">
            </form>
        </div>

        <!-- On ne fait plus d’alert() JS ici.
             On gère la modale s’il y a un paramètre `wingardium_subscribed=1` -->
        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------------------------------
       CONFIG SMTP
    ---------------------------------------------------------------------- */
    public function configure_smtp($phpmailer) {
        $smtp_enabled = get_option('wingardium_smtp_enabled','0');
        $smtp_host    = get_option('wingardium_smtp_host','');
        $smtp_port    = get_option('wingardium_smtp_port','');
        $smtp_enc     = get_option('wingardium_smtp_encryption','none');
        $smtp_user    = get_option('wingardium_smtp_username','');
        $smtp_pass    = get_option('wingardium_smtp_password','');

        $from_name  = get_option('wingardium_from_name', get_bloginfo('name'));
        $from_email = get_option('wingardium_from_address', get_bloginfo('admin_email'));
        $bcc_email  = get_option('wingardium_bcc_address','');

        $phpmailer->setFrom($from_email, $from_name);
        if(!empty($bcc_email)){
            $phpmailer->addBCC($bcc_email);
        }

        if($smtp_enabled==='1' && $smtp_host && $smtp_port){
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_host;
            $phpmailer->Port = (int)$smtp_port;

            if($smtp_enc==='ssl'){
                $phpmailer->SMTPSecure='ssl';
            } elseif($smtp_enc==='tls'){
                $phpmailer->SMTPSecure='tls';
            } else {
                $phpmailer->SMTPSecure='';
            }

            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $smtp_user;
            $phpmailer->Password = $smtp_pass;
            $phpmailer->CharSet  = 'UTF-8';
        }
    }
}

// Init plugin
WingardiumNewsletter::get_instance();


