<?php
// Tambah menu admin
add_action('admin_menu', function () {
    add_menu_page(
        'Kelola Klien Kami',          // Page title
        'Kelola Klien Kami',          // Menu title
        'manage_options',             // Capability
        'kelola-klien-kami',          // Slug
        'render_kelola_klien_page',   // Callback
        'dashicons-groups',           // Icon
        25                            // Position
    );
});

// Register setting
add_action('admin_init', function () {
    register_setting('kelola_klien_group', 'kelola_klien_textarea');
});

/* ================================
   2. Render Halaman + Save File
================================= */
function render_kelola_klien_page()
{
    if (!current_user_can('manage_options')) return;

    $upload_dir = wp_upload_dir();
    $folder = $upload_dir['basedir'] . '/klien-kami';
    wp_mkdir_p($folder);

    $option_key = 'klien_kami_categories';
    $categories = get_option($option_key, []);

    /* ==========================
       SAVE
    ========================== */
    if (isset($_POST['submit_klien'])) {

        check_admin_referer('save_klien_nonce');

        $new_categories = [];
        $posted_names   = $_POST['kategori'] ?? [];
        $posted_slugs   = $_POST['slug'] ?? [];
        $posted_clients = $_POST['clients'] ?? [];

        foreach ($posted_names as $index => $name) {

            $name = sanitize_text_field($name);
            if (!$name) continue;

            // kalau slug sudah ada pakai itu, kalau tidak generate baru
            $slug = !empty($posted_slugs[$index])
                ? sanitize_title($posted_slugs[$index])
                : sanitize_title($name);

            $file_path = $folder . '/' . $slug . '.txt';

            $content = isset($posted_clients[$index])
                ? wp_unslash($posted_clients[$index])
                : '';

            file_put_contents($file_path, trim($content));

            $new_categories[] = [
                'name' => $name,
                'slug' => $slug
            ];
        }

        update_option($option_key, $new_categories);

        echo '<div class="updated notice"><p>Data berhasil disimpan!</p></div>';

        $categories = $new_categories;
    }

    /* ==========================
       LOAD FILE CONTENT
    ========================== */
    if (empty($categories)) {
        $categories[] = ['name' => '', 'slug' => ''];
    }

?>
    <div class="wrap">
        <h1>Kelola Klien Kami</h1>

        <form method="post">
            <?php wp_nonce_field('save_klien_nonce'); ?>

            <div id="kategori-wrapper">
                <?php foreach ($categories as $cat):
                    $file_path = $folder . '/' . $cat['slug'] . '.txt';
                    $content = file_exists($file_path)
                        ? file_get_contents($file_path)
                        : '';
                ?>
                    <div class="kategori-item" style="border:1px solid #ddd;padding:15px;margin-bottom:15px;">

                        <p>
                            <strong>Nama Kategori</strong><br>
                            <input type="text" name="kategori[]"
                                value="<?php echo esc_attr($cat['name']); ?>"
                                style="width:100%;" />
                        </p>

                        <!-- slug hidden supaya file tetap konsisten -->
                        <input type="hidden" name="slug[]"
                            value="<?php echo esc_attr($cat['slug']); ?>" />

                        <p>
                            <strong>Daftar Klien</strong><br>
                            <textarea name="clients[]" rows="6" style="width:100%;"><?php echo esc_textarea($content); ?></textarea>
                        </p>

                        <p style="text-align:right;">
                            <button type="button" class="button remove-kategori">Hapus</button>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <p style="text-align:right;">
                <button type="button" class="button" id="add-kategori">
                    + Tambah Kategori
                </button>
            </p>

            <p style="text-align:right;">
                <button type="submit" name="submit_klien" class="button button-primary">
                    Simpan
                </button>
            </p>
        </form>

        <hr>
        <p><strong>Folder:</strong><br>
            <code><?php echo esc_html($folder); ?></code>
        </p>
    </div>

    <script>
        document.getElementById('add-kategori').addEventListener('click', function() {

            const wrapper = document.getElementById('kategori-wrapper');

            const html = `
            <div class="kategori-item" style="border:1px solid #ddd;padding:15px;margin-bottom:15px;">
                <p>
                    <strong>Nama Kategori</strong><br>
                    <input type="text" name="kategori[]" style="width:100%;" />
                </p>
                <input type="hidden" name="slug[]" value="" />
                <p>
                    <strong>Daftar Klien</strong><br>
                    <textarea name="clients[]" rows="6" style="width:100%;"></textarea>
                </p>
                <p style="text-align:right;">
                <button type="button" class="button remove-kategori">Hapus</button>
                </p>
            </div>
            `;

            wrapper.insertAdjacentHTML('beforeend', html);
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-kategori')) {

                const confirmDelete = confirm('Yakin ingin menghapus kategori ini?');

                if (confirmDelete) {
                    e.target.closest('.kategori-item').remove();
                }
            }
        });
    </script>

<?php
}


add_shortcode('daftar_klien_kami', function ($atts) {

    $atts = shortcode_atts([
        'kategori' => '' // optional filter
    ], $atts);

    $option_key = 'klien_kami_categories';
    $categories = get_option($option_key, []);

    if (empty($categories)) {
        return '<p>Belum ada data klien.</p>';
    }

    $upload_dir = wp_upload_dir();
    $folder = $upload_dir['basedir'] . '/klien-kami';

    $output = '<div class="klien-kami-wrapper">';

    foreach ($categories as $cat) {

        // filter jika parameter kategori digunakan
        if (!empty($atts['kategori']) && $atts['kategori'] !== $cat['slug']) {
            continue;
        }

        $file_path = $folder . '/' . $cat['slug'] . '.txt';

        if (!file_exists($file_path)) continue;

        $content = file_get_contents($file_path);
        $lines = array_filter(array_map('trim', explode("\n", $content)));

        if (empty($lines)) continue;

        $output .= '<div class="klien-kategori">';
        $output .= '<h3 style="margin-bottom:10px;">' . esc_html($cat['name']) . '</h3>';
        $output .= '<ol>';

        foreach ($lines as $line) {
            $output .= '<li>' . esc_html($line) . '</li>';
        }

        $output .= '</ol>';
        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
});
