<?php
/**
 * Plugin Name: İş Takip Sistemi
 * Plugin URI: https://wptr.com
 * Description: WordPress yönetim panelini kullanarak basit bir iş takip sistemi
 * Version: 1.0
 * Author: Halim KILIC
 * Author URI: https://wptr.com
 * Text Domain: is-takip-sistemi
 * Domain Path: /languages
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Plugin sınıfı
class Is_Takip_Sistemi {

    public function __construct() {
        // Dil dosyalarını yükle
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Custom Post Type oluştur
        add_action('init', array($this, 'register_post_type'));
        
        // Admin panel CSS'i ekle
        add_action('admin_head', array($this, 'admin_styles'));
        
        // Meta kutuları ekle
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Verileri kaydet
        add_action('save_post', array($this, 'save_meta_data'));
        
        // Admin kolonları ekle
        add_filter('manage_is_listesi_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_is_listesi_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        
        // Author rolüne izinleri ekle
        add_action('admin_init', array($this, 'add_author_capabilities'));
        
        // Yeni iş oluşturulduğunda e-posta gönder
        add_action('transition_post_status', array($this, 'send_notification_email'), 10, 3);
        
        // İşçilerin sadece kendi işlerini görmesini sağla
        add_action('pre_get_posts', array($this, 'filter_author_posts'));
        
        // İş detayı sayfasını düzenle
        add_action('template_redirect', array($this, 'redirect_is_single'));
        
        // Admin menülerini düzenle
        add_action('admin_menu', array($this, 'customize_admin_menu'), 999);
        
        // İş Formu sayfasına yönlendirme
        add_filter('post_row_actions', array($this, 'customize_row_actions'), 10, 2);
    }
    
    // Dil dosyalarını yükle
    public function load_textdomain() {
        load_plugin_textdomain('is-takip-sistemi', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    // Satır eylemlerini özelleştir (View yerine İş Formu yapalım)
    public function customize_row_actions($actions, $post) {
        // Sadece is_listesi post tipi için ve author rolü için
        if ($post->post_type === 'is_listesi' && !current_user_can('administrator')) {
            if (isset($actions['view'])) {
                $is_id = $post->ID;
                $actions['view'] = '<a href="' . esc_url(admin_url('admin.php?page=is-bitirme-formu&is_id=' . $is_id)) . '">' . __('İş Formu', 'is-takip-sistemi') . '</a>';
            }
        }
        return $actions;
    }
    
    // Admin menülerini özelleştir - Author rolünden gereksiz menüleri kaldır
    public function customize_admin_menu() {
        if (!current_user_can('administrator') && current_user_can('author')) {
            global $submenu;
            
            // "Yeni Ekle" alt menüsünü kaldır (is_listesi için)
            if (isset($submenu['edit.php?post_type=is_listesi'])) {
                foreach ($submenu['edit.php?post_type=is_listesi'] as $key => $item) {
                    if ($item[2] == 'post-new.php?post_type=is_listesi') {
                        unset($submenu['edit.php?post_type=is_listesi'][$key]);
                        break;
                    }
                }
            }
        }
    }

    // İş detayı sayfasını düzenle
    public function redirect_is_single() {
        if (is_singular('is_listesi') && !is_admin()) {
            global $post;
            
            // Eğer kullanıcı giriş yapmışsa
            if (is_user_logged_in()) {
                // Kullanıcı ID'sini al
                $current_user_id = get_current_user_id();
                
                // İş sorumlusunu al
                $is_sorumlusu = get_post_meta($post->ID, '_is_sorumlusu', true);
                
                // Eğer kullanıcı admin veya iş sorumlusu ise
                if (current_user_can('administrator') || $current_user_id == $is_sorumlusu) {
                    // İş formu sayfasına yönlendir
                    wp_redirect(admin_url('admin.php?page=is-bitirme-formu&is_id=' . $post->ID));
                    exit;
                }
            }
            
            // Diğer durumlarda ana sayfaya yönlendir
            wp_redirect(home_url());
            exit;
        }
    }
    
    // Custom Post Type oluştur
    public function register_post_type() {
        $labels = array(
            'name'               => __('İş Listesi', 'is-takip-sistemi'),
            'singular_name'      => __('İş', 'is-takip-sistemi'),
            'menu_name'          => __('İş Listesi', 'is-takip-sistemi'),
            'add_new'            => __('İş Ekle', 'is-takip-sistemi'),
            'add_new_item'       => __('Yeni İş Ekle', 'is-takip-sistemi'),
            'edit_item'          => __('İşi Düzenle', 'is-takip-sistemi'),
            'new_item'           => __('Yeni İş', 'is-takip-sistemi'),
            'view_item'          => __('İşi Görüntüle', 'is-takip-sistemi'),
            'search_items'       => __('İş Ara', 'is-takip-sistemi'),
            'not_found'          => __('İş bulunamadı', 'is-takip-sistemi'),
            'not_found_in_trash' => __('Çöp kutusunda iş bulunamadı', 'is-takip-sistemi'),
        );

        $capabilities = array(
            'edit_post'          => 'edit_is_listesi',
            'read_post'          => 'read_is_listesi',
            'delete_post'        => 'delete_is_listesi',
            'edit_posts'         => 'edit_is_listesis',
            'edit_others_posts'  => 'edit_others_is_listesis',
            'publish_posts'      => 'publish_is_listesis',
            'read_private_posts' => 'read_private_is_listesis',
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'is-listesi'),
            'capability_type'     => 'is_listesi',
            'capabilities'        => $capabilities,
            'map_meta_cap'        => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-clipboard',
            'supports'            => array('title', 'editor', 'author'),
        );

        register_post_type('is_listesi', $args);
    }

    // Admin panel CSS
    public function admin_styles() {
        ?>
        <style>
            .is-durum-devam-ediyor {
                background-color: #ffeb3b;
                color: #000;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: bold;
            }
            .is-durum-parca-bekliyor {
                background-color: #ff9800;
                color: #fff;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: bold;
            }
            .is-durum-tamamlandi {
                background-color: #4caf50;
                color: #fff;
                padding: 3px 8px;
                border-radius: 4px;
                font-weight: bold;
            }
        </style>
        <?php
    }

    // Meta box ekle
    public function add_meta_boxes() {
        add_meta_box(
            'is_takip_meta_box',
            __('İş Detayları', 'is-takip-sistemi'),
            array($this, 'render_meta_box'),
            'is_listesi',
            'normal',
            'high'
        );
    }

    // Meta box içeriği
    public function render_meta_box($post) {
        // Nonce field ekle
        wp_nonce_field('is_takip_meta_box', 'is_takip_meta_box_nonce');

        // Mevcut değerleri al
        $musteri_adi = get_post_meta($post->ID, '_musteri_adi', true);
        $musteri_adresi = get_post_meta($post->ID, '_musteri_adresi', true);
        $musteri_telefonu = get_post_meta($post->ID, '_musteri_telefonu', true);
        $is_durumu = get_post_meta($post->ID, '_is_durumu', true);
        $is_sorumlusu = get_post_meta($post->ID, '_is_sorumlusu', true);

        // Form alanlarını oluştur
        ?>
        <table class="form-table">
            <tr>
                <th><label for="musteri_adi"><?php _e('Müşteri Adı Soyadı:', 'is-takip-sistemi'); ?></label></th>
                <td><input type="text" name="musteri_adi" id="musteri_adi" value="<?php echo esc_attr($musteri_adi); ?>" style="width: 100%;" /></td>
            </tr>
            <tr>
                <th><label for="musteri_adresi"><?php _e('Müşteri Adresi:', 'is-takip-sistemi'); ?></label></th>
                <td><textarea name="musteri_adresi" id="musteri_adresi" rows="3" style="width: 100%;"><?php echo esc_textarea($musteri_adresi); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="musteri_telefonu"><?php _e('Müşteri Telefonu:', 'is-takip-sistemi'); ?></label></th>
                <td><input type="text" name="musteri_telefonu" id="musteri_telefonu" value="<?php echo esc_attr($musteri_telefonu); ?>" style="width: 100%;" /></td>
            </tr>
            <tr>
                <th><label for="is_durumu"><?php _e('İşin Durumu:', 'is-takip-sistemi'); ?></label></th>
                <td>
                    <select name="is_durumu" id="is_durumu" style="width: 100%;">
                        <option value="devam_ediyor" <?php selected($is_durumu, 'devam_ediyor'); ?>><?php _e('Devam Ediyor', 'is-takip-sistemi'); ?></option>
                        <option value="parca_bekliyor" <?php selected($is_durumu, 'parca_bekliyor'); ?>><?php _e('Parça Bekliyor', 'is-takip-sistemi'); ?></option>
                        <option value="tamamlandi" <?php selected($is_durumu, 'tamamlandi'); ?>><?php _e('Tamamlandı', 'is-takip-sistemi'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="is_sorumlusu"><?php _e('İşi Yapacak Eleman:', 'is-takip-sistemi'); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users(array(
                        'name' => 'is_sorumlusu',
                        'selected' => $is_sorumlusu,
                        'show_option_none' => __('Seçiniz', 'is-takip-sistemi'),
                        'option_none_value' => '',
                        'role__in' => array('author', 'administrator'),
                        'style' => 'width: 100%;'
                    ));
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    // Meta verileri kaydet
    public function save_meta_data($post_id) {
        // Güvenlik kontrolü
        if (!isset($_POST['is_takip_meta_box_nonce']) || !wp_verify_nonce($_POST['is_takip_meta_box_nonce'], 'is_takip_meta_box')) {
            return;
        }

        // Otomatik kaydetme sırasında kaydetme
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Kullanıcı izinlerini kontrol et
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Sadece is_listesi post tipi için işlem yap
        if (get_post_type($post_id) != 'is_listesi') {
            return;
        }

        // Müşteri adı soyadı kaydet
        if (isset($_POST['musteri_adi'])) {
            update_post_meta($post_id, '_musteri_adi', sanitize_text_field($_POST['musteri_adi']));
        }

        // Müşteri adresi kaydet
        if (isset($_POST['musteri_adresi'])) {
            update_post_meta($post_id, '_musteri_adresi', sanitize_textarea_field($_POST['musteri_adresi']));
        }

        // Müşteri telefonu kaydet
        if (isset($_POST['musteri_telefonu'])) {
            update_post_meta($post_id, '_musteri_telefonu', sanitize_text_field($_POST['musteri_telefonu']));
        }

        // İş durumu kaydet
        if (isset($_POST['is_durumu'])) {
            update_post_meta($post_id, '_is_durumu', sanitize_text_field($_POST['is_durumu']));
        }

        // İş sorumlusu kaydet
        if (isset($_POST['is_sorumlusu'])) {
            update_post_meta($post_id, '_is_sorumlusu', absint($_POST['is_sorumlusu']));
        }
    }

    // Admin paneli için özel kolonlar
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['musteri_bilgileri'] = __('Müşteri Bilgileri', 'is-takip-sistemi');
        $new_columns['is_durumu'] = __('İşin Durumu', 'is-takip-sistemi');
        $new_columns['is_sorumlusu'] = __('İşi Yapacak Eleman', 'is-takip-sistemi');
        $new_columns['date'] = $columns['date'];

        return $new_columns;
    }

    // Özel kolon içeriği
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'musteri_bilgileri':
                $adi = get_post_meta($post_id, '_musteri_adi', true);
                $adres = get_post_meta($post_id, '_musteri_adresi', true);
                $telefon = get_post_meta($post_id, '_musteri_telefonu', true);
                echo '<strong>' . __('Ad Soyad:', 'is-takip-sistemi') . '</strong> ' . esc_html($adi) . '<br>';
                echo '<strong>' . __('Adres:', 'is-takip-sistemi') . '</strong> ' . esc_html($adres) . '<br>';
                echo '<strong>' . __('Telefon:', 'is-takip-sistemi') . '</strong> ' . esc_html($telefon);
                break;

            case 'is_durumu':
                $durum = get_post_meta($post_id, '_is_durumu', true);
                $durum_text = '';
                $durum_class = '';

                switch ($durum) {
                    case 'devam_ediyor':
                        $durum_text = __('Devam Ediyor', 'is-takip-sistemi');
                        $durum_class = 'is-durum-devam-ediyor';
                        break;
                    case 'parca_bekliyor':
                        $durum_text = __('Parça Bekliyor', 'is-takip-sistemi');
                        $durum_class = 'is-durum-parca-bekliyor';
                        break;
                    case 'tamamlandi':
                        $durum_text = __('Tamamlandı', 'is-takip-sistemi');
                        $durum_class = 'is-durum-tamamlandi';
                        break;
                    default:
                        $durum_text = __('Belirlenmedi', 'is-takip-sistemi');
                        break;
                }

                echo '<span class="' . esc_attr($durum_class) . '">' . esc_html($durum_text) . '</span>';
                break;

            case 'is_sorumlusu':
                $sorumlu_id = get_post_meta($post_id, '_is_sorumlusu', true);
                if ($sorumlu_id) {
                    $user = get_userdata($sorumlu_id);
                    if ($user) {
                        echo esc_html($user->display_name);
                    }
                } else {
                    echo '—';
                }
                break;
        }
    }

    // Author rolüne izinler ekle
    public function add_author_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            $admin_role->add_cap('edit_is_listesi');
            $admin_role->add_cap('read_is_listesi');
            $admin_role->add_cap('delete_is_listesi');
            $admin_role->add_cap('edit_is_listesis');
            $admin_role->add_cap('edit_others_is_listesis');
            $admin_role->add_cap('publish_is_listesis');
            $admin_role->add_cap('read_private_is_listesis');
            $admin_role->add_cap('delete_is_listesis');
            $admin_role->add_cap('delete_private_is_listesis');
            $admin_role->add_cap('delete_published_is_listesis');
            $admin_role->add_cap('delete_others_is_listesis');
            $admin_role->add_cap('edit_private_is_listesis');
            $admin_role->add_cap('edit_published_is_listesis');
        }
        
        $author_role = get_role('author');
        
        if ($author_role) {
            $author_role->add_cap('read_is_listesi');
            $author_role->add_cap('edit_is_listesi');
            $author_role->add_cap('edit_is_listesis');
            $author_role->add_cap('read_private_is_listesis');
            $author_role->add_cap('edit_published_is_listesis');
            
            // Yazar rolü için yayınlama yetkisini kaldır
            $author_role->remove_cap('publish_is_listesis');
            $author_role->remove_cap('create_is_listesis');
        }
    }


	// E-posta bildirimi gönder
public function send_notification_email($new_status, $old_status, $post) {
    // Sadece "is_listesi" post tipi için işlem yap
    if ($post->post_type != 'is_listesi') {
        return;
    }

    // Yeni iş oluşturulduğunda veya iş sorumlusu değiştirildiğinde SADECE
    if (($new_status == 'publish' && $old_status != 'publish') || 
        ($new_status == 'publish' && $old_status == 'publish' && isset($_POST['is_sorumlusu']))) {
        
        // İş sorumlusunu al
        $sorumlu_id = get_post_meta($post->ID, '_is_sorumlusu', true);
        
        if (!$sorumlu_id) {
            return;
        }

        $sorumlu = get_userdata($sorumlu_id);
        
        if (!$sorumlu) {
            return;
        }

        // Müşteri bilgilerini al
        $musteri_adi = get_post_meta($post->ID, '_musteri_adi', true);
        $musteri_adresi = get_post_meta($post->ID, '_musteri_adresi', true);
        $musteri_telefonu = get_post_meta($post->ID, '_musteri_telefonu', true);
        $is_tanimi = get_the_title($post->ID);
        $is_aciklamasi = $post->post_content;

        // E-posta başlığı ve içeriği
        $subject = 'YENİ BİR İŞ TANIMLANDI: ' . $is_tanimi;
        
        $message = '<h2>Yeni Bir İş Tanımlandı</h2>';
        $message .= '<p><strong>İş Başlığı:</strong> ' . $is_tanimi . '</p>';
        $message .= '<p><strong>Müşteri Adı Soyadı:</strong> ' . $musteri_adi . '</p>';
        $message .= '<p><strong>Müşteri Adresi:</strong> ' . $musteri_adresi . '</p>';
        $message .= '<p><strong>Müşteri Telefonu:</strong> ' . $musteri_telefonu . '</p>';
        $message .= '<p><strong>Yapılacak İşin Tanımı:</strong> ' . $is_aciklamasi . '</p>';
        $message .= '<p>İş formunu görüntülemek için <a href="' . admin_url('admin.php?page=is-bitirme-formu&is_id=' . $post->ID) . '">tıklayınız</a>.</p>';

        // E-posta başlıkları
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // E-postayı gönder
        $sent = wp_mail($sorumlu->user_email, $subject, $message, $headers);
        
        if ($sent) {
            // E-posta gönderildi, log kaydı tutabiliriz
            error_log('Yeni iş e-postası gönderildi: ' . $sorumlu->user_email);
        } else {
            // E-posta gönderilemedi, sorunu loglayalım
            error_log('Yeni iş e-postası gönderilemedi: ' . $sorumlu->user_email);
        }
    }
    
    // ÖNEMLİ: İş durumu değiştirildiğinde işçiye e-posta gönderme kodunu kaldırdık
}

    // İşçilerin sadece kendi işlerini görmesini sağlayan filtre
    public function filter_author_posts($query) {
        // Admin olmayan kullanıcılar için sadece kendi işlerini göster
        if (!current_user_can('administrator') && is_admin()) {
            global $pagenow, $typenow;
            
            // Sadece is_listesi post tipi için ve sadece kullanıcıyı ilgilendiren sayfalarda
            if (($pagenow == 'edit.php' && $typenow == 'is_listesi') || 
                ($pagenow == 'post.php' && isset($_GET['post']) && get_post_type($_GET['post']) == 'is_listesi')) {
                
                $current_user_id = get_current_user_id();
                
                // Meta sorgusu ayarla
                $query->set('meta_query', array(
                    array(
                        'key'     => '_is_sorumlusu',
                        'value'   => $current_user_id,
                        'compare' => '='
                    )
                ));
            }
        }
    }
}

// Aktivasyon hook
register_activation_hook(__FILE__, 'is_takip_sistemi_activate');

function is_takip_sistemi_activate() {
    // Özel post tipi oluştur
    $plugin = new Is_Takip_Sistemi();
    $plugin->register_post_type();
    
    // Yetkiler ekle
    $plugin->add_author_capabilities();
    
    // Rewrite kurallarını güncelle
    flush_rewrite_rules();
}

// Deaktivasyon hook
register_deactivation_hook(__FILE__, 'is_takip_sistemi_deactivate');

function is_takip_sistemi_deactivate() {
    // Rewrite kurallarını temizle
    flush_rewrite_rules();
    
    // Yetkileri temizle
    $author_role = get_role('author');
    if ($author_role) {
        $author_role->remove_cap('read_is_listesi');
        $author_role->remove_cap('edit_is_listesi');
        $author_role->remove_cap('edit_is_listesis');
        $author_role->remove_cap('read_private_is_listesis');
        $author_role->remove_cap('publish_is_listesis');
        $author_role->remove_cap('edit_published_is_listesis');
        $author_role->remove_cap('create_is_listesis');
    }
}

// Plugin örneği oluştur
$is_takip_sistemi = new Is_Takip_Sistemi();
