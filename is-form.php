<?php
/**
 * Plugin Name: İş Bitirme Formu
 * Plugin URI: https://wptr.com
 * Description: İş Takip Sistemi eklentisi için iş bitirme formu ve imza özellikleri
 * Version: 1.0
 * Author: Halim KILIC
 * Author URI: https://wptr.com
 * Text Domain: is-bitirme-formu
 */

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Ana sınıf
class Is_Bitirme_Formu {

    public function __construct() {
        // Meta kutuları ekle
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        
        // Admin menü sayfasını ekle
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Script ve stilleri ekle
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    // Script ve stilleri ekle
    public function enqueue_scripts($hook) {
        // Sadece bizim sayfamızda yükle
        if ($hook != 'is_listesi_page_is-bitirme-formu') {
            return;
        }
        
        // SignaturePad kütüphanesini CDN'den yükle
        wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), '4.0.0', true);
        
        // İmza için JavaScript
        add_action('admin_footer', array($this, 'add_signature_script'));
    }
    
    // İmza için JavaScript
    public function add_signature_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // İmza alanı
            var canvas = document.getElementById("signatureCanvas");
            
            if (canvas) {
                var signaturePad = new SignaturePad(canvas, {
                    backgroundColor: "rgb(255, 255, 255)",
                    penColor: "rgb(0, 0, 0)"
                });
                
                // Canvas boyutunu ayarla
                function resizeCanvas() {
                    var ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.clear();
                }
                
                window.addEventListener("resize", resizeCanvas);
                resizeCanvas();
                
                // Temizle butonu
                $("#clearSignature").on("click", function(e) {
                    e.preventDefault();
                    signaturePad.clear();
                });
                
                // Form gönderimi
                $("#isbitirmeFormu").on("submit", function(e) {
                    if (signaturePad.isEmpty()) {
                        alert("Lütfen müşteri imzasını alınız!");
                        e.preventDefault();
                        return false;
                    }
                    
                    // İmza verisini gizli alana ekle
                    $("#signature_data").val(signaturePad.toDataURL());
                });
            }
        });
        </script>
        <?php
    }
    
    // Meta box ekle
    public function add_meta_boxes() {
        // Post Type kontrolü
        if (!post_type_exists('is_listesi')) {
            return;
        }
        
        add_meta_box(
            'is_bitirme_meta_box',
            'İş Bitirme Formu',
            array($this, 'render_meta_box'),
            'is_listesi',
            'normal',
            'high'
        );
    }
    
    // Meta box içeriği
    public function render_meta_box($post) {
        // Post ID'yi al
        $post_id = $post->ID;
        
        // Bilgileri getir
        $musteri_adi = get_post_meta($post_id, '_musteri_adi', true);
        $musteri_imza = get_post_meta($post_id, '_musteri_imza', true);
        
        ?>
        <div class="is-bitirme-form-container">
            <h3>Müşteri İş Bitirme Bilgileri</h3>
            
            <?php if (!empty($musteri_imza)) : ?>
                <p><strong>Müşteri İmzası:</strong></p>
                <img src="<?php echo esc_attr($musteri_imza); ?>" style="max-width: 300px; border: 1px solid #ddd;" />
            <?php endif; ?>
            
            <p>İş bitirme formunu görüntülemek için <a href="<?php echo esc_url(add_query_arg('is_id', $post_id, admin_url('admin.php?page=is-bitirme-formu'))); ?>">tıklayın</a></p>
        </div>
        <?php
    }
    
    // Admin menü sayfası
    public function add_admin_menu() {
        // İş Listesi post tipi varsa menüye ekle
        if (post_type_exists('is_listesi')) {
            add_submenu_page(
                'edit.php?post_type=is_listesi',
                'İş Bitirme Formu',
                'İş Bitirme Formu',
                'edit_posts',
                'is-bitirme-formu',
                array($this, 'render_admin_page')
            );
        }
    }
    
    // Admin sayfa içeriği
    public function render_admin_page() {
        // İş ID'sini al
        $is_id = isset($_GET['is_id']) ? intval($_GET['is_id']) : 0;
        
        echo '<div class="wrap">';
        echo '<h1>İş Bitirme Formu</h1>';
        
        if ($is_id > 0) {
            // Belirli bir iş için form göster
            $this->render_is_formu($is_id);
        } else {
            // İş listesi göster
            $this->render_is_listesi();
        }
        
        echo '</div>';
    }
    
    // İş formunu göster
    private function render_is_formu($post_id) {
        // Post tipi kontrolü
        $post = get_post($post_id);
        if (!$post || $post->post_type != 'is_listesi') {
            echo '<div class="notice notice-error"><p>Belirtilen ID\'ye sahip iş bulunamadı.</p></div>';
            return;
        }
        
        // Kullanıcı yetkisi kontrolü
        $current_user = wp_get_current_user();
        $is_sorumlusu = get_post_meta($post_id, '_is_sorumlusu', true);
        
        if (!current_user_can('administrator') && $current_user->ID != $is_sorumlusu) {
            echo '<div class="notice notice-error"><p>Bu işi görüntüleme yetkiniz bulunmamaktadır.</p></div>';
            return;
        }
        
        // İş verileri
        $is_baslik = get_the_title($post_id);
        $is_aciklama = $post->post_content;
        $musteri_adi = get_post_meta($post_id, '_musteri_adi', true);
        $musteri_adresi = get_post_meta($post_id, '_musteri_adresi', true);
        $musteri_telefonu = get_post_meta($post_id, '_musteri_telefonu', true);
        $is_durumu = get_post_meta($post_id, '_is_durumu', true);
        $musteri_imza = get_post_meta($post_id, '_musteri_imza', true);
        
        // Başarı mesajı kontrolü
        if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>İş bilgileri başarıyla güncellendi.</p></div>';
        }
        
        ?>
        <div class="is-bitirme-form-container" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="isbitirmeFormu" enctype="multipart/form-data">
                <input type="hidden" name="action" value="kaydet_is_durumu">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input type="hidden" name="signature_data" id="signature_data" value="">
                <?php wp_nonce_field('is_bitirme_form_nonce', 'is_bitirme_nonce'); ?>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #23282d;">İşin Durumu</h3>
                    <div>
                        <label for="is_durumu">İşin Durumu:</label>
                        <select name="is_durumu" id="is_durumu" required style="width: 100%;">
                            <option value="devam_ediyor" <?php selected($is_durumu, 'devam_ediyor'); ?>>Devam Ediyor</option>
                            <option value="parca_bekliyor" <?php selected($is_durumu, 'parca_bekliyor'); ?>>Parça Bekliyor</option>
                            <option value="tamamlandi" <?php selected($is_durumu, 'tamamlandi'); ?>>Tamamlandı</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #23282d;">İş Bilgileri</h3>
                    <p><strong>İşin Adı:</strong> <?php echo esc_html($is_baslik); ?></p>
                    <p><strong>İşin Açıklaması:</strong> <?php echo wpautop(esc_html($is_aciklama)); ?></p>
                </div>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #23282d;">Müşteri Bilgileri</h3>
                    <p><strong>Müşteri Adı Soyadı:</strong> <?php echo esc_html($musteri_adi); ?></p>
                    <p><strong>Müşteri Adresi:</strong> <?php echo esc_html($musteri_adresi); ?></p>
                    <p><strong>Müşteri Telefonu:</strong> <?php echo esc_html($musteri_telefonu); ?></p>
                </div>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #23282d;">İş Fotoğrafları</h3>
                    
                    <?php
                    // Daha önce yüklenmiş dosyaları göster
                    $is_fotograflari = get_post_meta($post_id, '_is_fotograflari', true);
                    if (!empty($is_fotograflari) && is_array($is_fotograflari)) {
                        echo '<div style="margin-bottom: 20px;">';
                        echo '<p><strong>Yüklenen Fotoğraflar:</strong></p>';
                        echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
                        
                        foreach ($is_fotograflari as $foto_id) {
                            $foto_url = wp_get_attachment_url($foto_id);
                            $foto_path = get_attached_file($foto_id);
                            $foto_name = basename($foto_path);
                            
                            echo '<div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px; width: 200px;">';
                            
                            // Resim ise küçük bir önizleme göster
                            if (wp_attachment_is_image($foto_id)) {
                                $thumbnail = wp_get_attachment_image($foto_id, 'thumbnail');
                                echo '<div style="margin-bottom: 5px;">' . $thumbnail . '</div>';
                            } else {
                                // Resim değilse dosya ikonu göster
                                echo '<div style="margin-bottom: 5px;"><span class="dashicons dashicons-media-default" style="font-size: 40px; width: 40px; height: 40px;"></span></div>';
                            }
                            
                            echo '<div><a href="' . esc_url($foto_url) . '" target="_blank">' . esc_html($foto_name) . '</a></div>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                    }
                    ?>
                    
                    <p>İşle ilgili fotoğrafları yükleyiniz:</p>
                    <input type="file" name="is_fotograflari[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
                    <p class="description">Aynı anda birden fazla dosya seçebilirsiniz. İzin verilen dosya formatları: Resimler, PDF, DOC, DOCX, XLS, XLSX, TXT.</p>
                </div>
                
                <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                    <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 18px; color: #23282d;">Müşteri İmzası</h3>
                    
                    <?php if (!empty($musteri_imza)) : ?>
                        <p>Mevcut imza:</p>
                        <img src="<?php echo esc_attr($musteri_imza); ?>" style="max-width: 300px; border: 1px solid #ddd; margin-bottom: 15px;" />
                    <?php endif; ?>
                    
                    <p>Lütfen aşağıdaki alana müşteri imzasını alınız:</p>
                    <div style="border: 1px solid #ddd; background: #fff; margin-bottom: 10px;">
                        <canvas id="signatureCanvas" width="600" height="200" style="width: 100%; height: 200px;"></canvas>
                    </div>
                    <button id="clearSignature" class="button" style="margin-bottom: 15px;">İmzayı Temizle</button>
                </div>
                
                <div style="text-align: right; margin-top: 20px;">
                    <input type="submit" class="button button-primary" value="Kaydet">
                </div>
            </form>
        </div>
        <?php
    }
    
    // İş listesini göster
    private function render_is_listesi() {
        // İş listesi göster
        $args = array(
            'post_type' => 'is_listesi',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Sadece kullanıcıya atanan işleri göster (admin değilse)
        if (!current_user_can('administrator')) {
            $current_user = wp_get_current_user();
            $args['meta_query'] = array(
                array(
                    'key' => '_is_sorumlusu',
                    'value' => $current_user->ID,
                    'compare' => '='
                )
            );
        }
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>İş Başlığı</th>';
            echo '<th>Müşteri Bilgileri</th>';
            echo '<th>İşin Durumu</th>';
            echo '<th>İmza Durumu</th>';
            echo '<th>İşlemler</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $musteri_adi = get_post_meta($post_id, '_musteri_adi', true);
                $musteri_adresi = get_post_meta($post_id, '_musteri_adresi', true);
                $musteri_telefonu = get_post_meta($post_id, '_musteri_telefonu', true);
                $is_durumu = get_post_meta($post_id, '_is_durumu', true);
                $musteri_imza = get_post_meta($post_id, '_musteri_imza', true);
                
                $durum_metni = '';
                $durum_style = '';
                
                switch ($is_durumu) {
                    case 'devam_ediyor':
                        $durum_metni = 'Devam Ediyor';
                        $durum_style = 'background-color: #ffeb3b; color: #000; padding: 3px 8px; border-radius: 4px; font-weight: bold;';
                        break;
                    case 'parca_bekliyor':
                        $durum_metni = 'Parça Bekliyor';
                        $durum_style = 'background-color: #ff9800; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: bold;';
                        break;
                    case 'tamamlandi':
                        $durum_metni = 'Tamamlandı';
                        $durum_style = 'background-color: #4caf50; color: #fff; padding: 3px 8px; border-radius: 4px; font-weight: bold;';
                        break;
                    default:
                        $durum_metni = 'Belirlenmedi';
                        break;
                }
                
                echo '<tr>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . esc_html($musteri_adi) . '<br>' . esc_html($musteri_adresi) . '<br><strong>Tel:</strong> ' . esc_html($musteri_telefonu) . '</td>';
                echo '<td><span style="' . $durum_style . '">' . esc_html($durum_metni) . '</span></td>';
                echo '<td>' . (empty($musteri_imza) ? 'İmza alınmadı' : 'İmza alındı') . '</td>';
                echo '<td><a href="' . esc_url(add_query_arg('is_id', $post_id, admin_url('admin.php?page=is-bitirme-formu'))) . '" class="button">İş Bitirme Formu</a></td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            
            wp_reset_postdata();
        } else {
            echo '<p>Hiç iş kaydı bulunamadı.</p>';
        }
    }
}

// Admin post işleyicisini ekle
add_action('admin_init', 'is_bitirme_formu_register_handler');

function is_bitirme_formu_register_handler() {
    add_action('admin_post_kaydet_is_durumu', 'is_bitirme_formu_save_handler');
}
// Form verilerini kaydet
function is_bitirme_formu_save_handler() {
    // Nonce kontrolü
    if (!isset($_POST['is_bitirme_nonce']) || !wp_verify_nonce($_POST['is_bitirme_nonce'], 'is_bitirme_form_nonce')) {
        wp_die('Güvenlik doğrulaması başarısız.');
    }
    
    // Post ID
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id <= 0) {
        wp_die('Geçersiz iş ID\'si.');
    }
    
    // Kullanıcı yetkisi kontrolü
    $current_user_id = get_current_user_id();
    $is_sorumlusu = get_post_meta($post_id, '_is_sorumlusu', true);
    
    if (!current_user_can('administrator') && $current_user_id != $is_sorumlusu) {
        wp_die('Bu işi düzenleme yetkiniz bulunmamaktadır.');
    }
    
    // İş durumunu kaydet
    if (isset($_POST['is_durumu'])) {
        update_post_meta($post_id, '_is_durumu', sanitize_text_field($_POST['is_durumu']));
    }
    
    // İmza verilerini kaydet
    if (isset($_POST['signature_data']) && !empty($_POST['signature_data'])) {
        update_post_meta($post_id, '_musteri_imza', $_POST['signature_data']);
    }
    
    // Dosya yükleme işlemi
    if (!empty($_FILES['is_fotograflari']['name'][0])) {
        $uploaded_files = array();
        
        // Her dosya için döngü
        $files = $_FILES['is_fotograflari'];
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] == 0) {
                $file = array(
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                );
                
                // WordPress dosya yükleme işlemi
                $upload = wp_handle_upload($file, array('test_form' => false));
                
                if (!isset($upload['error'])) {
                    $filetype = wp_check_filetype($upload['file'], null);
                    
                    $attachment = array(
                        'guid'           => $upload['url'],
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
                        'post_content'   => '',
                        'post_status'    => 'inherit'
                    );
                    
                    // Dosyayı medya kütüphanesine ekle
                    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
                    
                    if (!is_wp_error($attachment_id)) {
                        // Resim dosyası ise küçük boyutlarını oluştur
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                        wp_update_attachment_metadata($attachment_id, $attachment_data);
                        
                        // Başarıyla yüklenen dosyaları listeye ekle
                        $uploaded_files[] = $attachment_id;
                    }
                }
            }
        }
        
        // Daha önce yüklenmiş dosyaları al ve yeni yüklenenlerle birleştir
        $existing_files = get_post_meta($post_id, '_is_fotograflari', true);
        if (!empty($existing_files) && is_array($existing_files)) {
            $uploaded_files = array_merge($existing_files, $uploaded_files);
        }
        
        // Dosya ID'lerini kaydet
        if (!empty($uploaded_files)) {
            update_post_meta($post_id, '_is_fotograflari', $uploaded_files);
        }
    }
    
    // SADECE Admin'e e-posta gönder
    $admin_email = get_option('admin_email');
    $is_baslik = get_the_title($post_id);
    $musteri_adi = get_post_meta($post_id, '_musteri_adi', true);
    $is_durumu = get_post_meta($post_id, '_is_durumu', true);
    $musteri_imza = get_post_meta($post_id, '_musteri_imza', true);
    
    $durum_metni = '';
    switch ($is_durumu) {
        case 'devam_ediyor':
            $durum_metni = 'Devam Ediyor';
            break;
        case 'parca_bekliyor':
            $durum_metni = 'Parça Bekliyor';
            break;
        case 'tamamlandi':
            $durum_metni = 'Tamamlandı';
            break;
        default:
            $durum_metni = 'Belirlenmedi';
            break;
    }
    
    $subject = 'İş Bitirme Formu Dolduruldu: ' . $is_baslik;
    
    $message = '<h2>İş Bitirme Formu Dolduruldu</h2>';
    $message .= '<p><strong>İş Başlığı:</strong> ' . $is_baslik . '</p>';
    $message .= '<p><strong>Müşteri Adı Soyadı:</strong> ' . $musteri_adi . '</p>';
    $message .= '<p><strong>İşin Durumu:</strong> ' . $durum_metni . '</p>';
    
    // Yüklenen dosyaları e-postaya ekle
    $is_fotograflari = get_post_meta($post_id, '_is_fotograflari', true);
    if (!empty($is_fotograflari) && is_array($is_fotograflari)) {
        $message .= '<p><strong>Yüklenen Dosyalar:</strong></p>';
        $message .= '<ul>';
        
        foreach ($is_fotograflari as $foto_id) {
            $foto_url = wp_get_attachment_url($foto_id);
            $foto_path = get_attached_file($foto_id);
            $foto_name = basename($foto_path);
            
            $message .= '<li><a href="' . esc_url($foto_url) . '">' . esc_html($foto_name) . '</a>';
            
            // Eğer resim dosyası ise, küçük bir önizleme ekle
            if (wp_attachment_is_image($foto_id)) {
                $thumbnail_url = wp_get_attachment_image_src($foto_id, 'thumbnail');
                if ($thumbnail_url) {
                    $message .= '<br><img src="' . esc_url($thumbnail_url[0]) . '" alt="' . esc_attr($foto_name) . '" style="max-width: 100px; max-height: 100px; margin: 5px 0;" />';
                }
            }
            
            $message .= '</li>';
        }
        
        $message .= '</ul>';
    }
    
    if (!empty($musteri_imza)) {
        $message .= '<p><strong>Müşteri İmzası:</strong></p>';
        $message .= '<img src="' . $musteri_imza . '" style="max-width: 400px; border: 1px solid #ddd;" />';
    }
    
    $message .= '<p>İş detaylarını görmek için <a href="' . admin_url('post.php?post=' . $post_id . '&action=edit') . '">tıklayınız</a>.</p>';
    
    // E-posta başlıkları
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // SADECE Admin'e e-postayı gönder
    wp_mail($admin_email, $subject, $message, $headers);
    
    // Yönlendirme
    wp_redirect(add_query_arg(array('page' => 'is-bitirme-formu', 'is_id' => $post_id, 'updated' => 'true'), admin_url('admin.php')));
    exit;
}

// Plugin örneği oluştur
$is_bitirme_formu = new Is_Bitirme_Formu();
