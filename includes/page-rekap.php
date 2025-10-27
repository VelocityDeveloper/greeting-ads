<?php
add_action('admin_menu', 'velocity_add_admin_page');

function velocity_add_admin_page()
{
  add_menu_page(
    'Rekap Chat Form',
    'Rekap Chat',
    'manage_options',
    'rekap-chat-form',
    'velocity_render_admin_page',
    'dashicons-format-chat',
    25
  );
}

// READ + FORM + CREATE + UPDATE + DELETE HANDLING
function velocity_render_admin_page()
{
  date_default_timezone_set('Asia/Jakarta');
  global $wpdb;
  $table_name = $wpdb->prefix . 'rekap_form';

  // Handle Create/Update
  if (isset($_POST['nama']) && isset($_POST['no_whatsapp']) && isset($_POST['jenis_website'])) {
    check_admin_referer('velocity_crud_action');

    $data = [
      'nama' => sanitize_text_field($_POST['nama']),
      'no_whatsapp' => sanitize_text_field($_POST['no_whatsapp']),
      'jenis_website' => sanitize_text_field($_POST['jenis_website']),
      'via' => sanitize_text_field($_POST['via']),
      'utm_content' => sanitize_text_field($_POST['utm_content']),
      'utm_medium' => sanitize_text_field($_POST['utm_medium']),
      'greeting' => sanitize_text_field($_POST['greeting']),
    ];

    if (!empty($_POST['id'])) {
      $wpdb->update($table_name, $data, ['id' => intval($_POST['id'])]);
      echo '<div class="updated"><p>Data berhasil diperbarui.</p></div>';
    } else {
      $data['created_at'] = current_time('mysql');
      $wpdb->insert($table_name, $data);
      echo '<div class="updated"><p>Data berhasil ditambahkan.</p></div>';
    }
  }

  // Handle Delete (single)
  if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $wpdb->delete($table_name, ['id' => $id]);
    echo '<div class="updated"><p>Data berhasil dihapus.</p></div>';
  }

  // Handle Bulk Delete
  if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'delete' && !empty($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $id_placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($id_placeholders)", ...$ids));
    echo '<div class="updated"><p>' . count($ids) . ' data berhasil dihapus.</p></div>';
  }

  // Load data untuk edit
  $edit_data = null;
  if (isset($_GET['edit'])) {
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", intval($_GET['edit'])));
  }
  // Filter Setup
  $selected_greeting = isset($_GET['filter_greeting']) ? sanitize_text_field($_GET['filter_greeting']) : '';

  // Build WHERE clause for filtering
  $where_clause = "WHERE 1=1";
  if (!empty($selected_greeting)) {
    $where_clause .= $wpdb->prepare(" AND greeting LIKE %s", '%' . $wpdb->esc_like($selected_greeting) . '%');
  }

  // Pagination Setup
  $per_page = 40;
  $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
  $offset = ($current_page - 1) * $per_page;

  $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
  $total_pages = ceil($total_items / $per_page);

  $results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
    $per_page,
    $offset
  ));
?>
  <div class="wrap">
    <?php if ($edit_data): ?>
      <script>
        document.addEventListener("DOMContentLoaded", function() {
          openModal();
        });
      </script>
    <?php endif; ?>
    <div id="modalForm" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
      <div style="background:#fff; margin:5% auto; padding:20px; width:90%; max-width:700px; border-radius:8px; position:relative;">
        <button onclick="closeModal()" style="position:absolute; top:10px; right:10px;">✕</button>
        <h2><?php echo $edit_data ? 'Edit Data' : 'Tambah Data'; ?></h2>
        <form method="post">
          <?php wp_nonce_field('velocity_crud_action'); ?>
          <?php if ($edit_data): ?>
            <input type="hidden" name="id" value="<?php echo intval($edit_data->id); ?>">
          <?php endif; ?>
          <table class="form-table">
            <tr>
              <th><label for="nama">Nama</label></th>
              <td><input name="nama" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->nama ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="no_whatsapp">No WhatsApp</label></th>
              <td><input name="no_whatsapp" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->no_whatsapp ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="jenis_website">Jenis Website</label></th>
              <td><input name="jenis_website" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->jenis_website ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="via">Via</label></th>
              <td><input name="via" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->via ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="utm_content">UTM Content</label></th>
              <td><input name="utm_content" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->utm_content ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="utm_medium">UTM Medium</label></th>
              <td><input name="utm_medium" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->utm_medium ?? ''); ?>"></td>
            </tr>
            <tr>
              <th><label for="greeting">Greeting</label></th>
              <td><input name="greeting" type="text" class="regular-text" value="<?php echo esc_attr($edit_data->greeting ?? ''); ?>"></td>
            </tr>
          </table>
          <?php submit_button($edit_data ? 'Update' : 'Tambah'); ?>
        </form>
      </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <div style="display: flex; gap: 10px; align-items: center;">
        <button type="button" class="button button-primary" onclick="openModal()">+ Tambah Data</button>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" style="display: inline;">
          <input type="hidden" name="action" value="velocity_export_to_excel">
          <button type="submit" class="button button-primary">
            <span style="margin-top: 5px;" class="dashicons dashicons-download"></span> Export Excel</button>
        </form>
      </div>

      <!-- Filter Greeting -->
      <form method="get" action="" style="display: flex; align-items: center; gap: 10px;">
        <input type="hidden" name="page" value="rekap-chat-form">
        <label for="filter_greeting" style="font-weight: bold;">Filter Greeting:</label>
        <input
          type="text"
          name="filter_greeting"
          id="filter_greeting"
          value="<?php echo htmlspecialchars($selected_greeting); ?>"
          placeholder="Contoh: v5008"
          style="padding: 5px; border: 1px solid #ccc; border-radius: 3px; width: 200px;"
        >
        <button type="submit" class="button button-primary">Filter</button>
        <button type="button" class="button" onclick="clearFilter()">Clear</button>
      </form>
    </div>

    <?php if (!empty($selected_greeting)): ?>
      <div style="background: #e7f3ff; border: 1px solid #2271b1; border-radius: 4px; padding: 8px 12px; margin-bottom: 15px;">
        <strong>Filter Aktif:</strong>
        Greeting = <?php echo htmlspecialchars($selected_greeting); ?>
        <a href="?page=rekap-chat-form" style="float: right; color: #d63638;">✕ Hapus Filter</a>
        <div style="margin-top: 5px; font-size: 12px; color: #50575e;">
          Menampilkan <strong><?php echo $total_items; ?></strong> data
        </div>
      </div>
    <?php elseif ($total_items > 0): ?>
      <div style="margin-bottom: 10px; color: #50575e;">
        Menampilkan <strong><?php echo $total_items; ?></strong> data
      </div>
    <?php endif; ?>

    <form method="post">

      <div style="display: flex; justify-content:space-between;">
        <button type="submit" id="cek-jenis-website" class="button button-primary">Cek Jenis website</button>
        <?php
        submit_button('Hapus yang dipilih', 'delete', '', false);
        ?>
      </div>
      <br>
      <input type="hidden" name="bulk_action" value="delete">
      <table class="widefat fixed striped" style="margin: 10px 0;">
        <thead>
          <tr>
            <th style="text-align: center;width: 40px;"><input style="margin: 0;" type="checkbox" id="select_all"></th>
            <th>Nama</th>
            <th>No WhatsApp</th>
            <th>Jenis Website</th>
            <th>Perangkat</th>
            <th>UTM Content</th>
            <th>UTM Medium</th>
            <th>Greeting</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($results): foreach ($results as $row): ?>
              <tr>
                <td style="text-align: center;"><input type="checkbox" name="selected_ids[]" value="<?php echo intval($row->id); ?>"></td>
                <td><?php echo esc_html($row->nama); ?></td>
                <td><?php echo esc_html($row->no_whatsapp);
                    echo show_valid_wa_icon($row->no_whatsapp); ?></td>
                <td>
                  <?php echo esc_html($row->jenis_website); ?>
                  <span class="ai-result" data-id="<?php echo intval($row->id); ?>">
                    <?php echo format_ai_result($row->ai_result); ?>
                  </span>
                </td>
                <td><?php echo esc_html($row->via); ?></td>
                <td><?php echo esc_html($row->utm_content); ?></td>
                <td><?php echo esc_html($row->utm_medium); ?></td>
                <td><?php echo esc_html($row->greeting); ?></td>
                <td><?php echo esc_html($row->created_at); ?></td>
                <td>
                  <a href="?page=rekap-chat-form&edit=<?php echo intval($row->id); ?>">Edit</a> |
                  <a href="?page=rekap-chat-form&delete=<?php echo intval($row->id); ?>" onclick="return confirm('Hapus data ini?')">Hapus</a>
                </td>
              </tr>
            <?php endforeach;
          else: ?>
            <tr>
              <td colspan="10">
                <?php if (!empty($selected_greeting)): ?>
                  Tidak ada data dengan greeting "<strong><?php echo htmlspecialchars($selected_greeting); ?></strong>".
                  <br><a href="?page=rekap-chat-form">Tampilkan semua data</a>
                <?php else: ?>
                  Belum ada data.
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </form>

    <?php if ($total_pages > 1): ?>
      <div class="tablenav bottom">
        <div class="tablenav-pages">
          <?php
          $display_pages = [];
          // Always show first 5
          for ($i = 1; $i <= min(5, $total_pages); $i++) {
            $display_pages[] = $i;
          }
          // Always show last 3
          for ($i = max($total_pages - 2, 1); $i <= $total_pages; $i++) {
            $display_pages[] = $i;
          }
          // Show 2 before and after current page
          for ($i = max($current_page - 2, 1); $i <= min($current_page + 2, $total_pages); $i++) {
            $display_pages[] = $i;
          }
          $display_pages = array_unique($display_pages);
          sort($display_pages);

          $last = 0;
          for ($idx = 0; $idx < count($display_pages); $idx++) {
            $i = $display_pages[$idx];
            if ($last && $i > $last + 1) {
              echo '<span class="page-numbers dots" style="padding: 10px;">...</span>';
            }
            if ($i == $current_page) {
              echo '<span class="page-numbers current" style="padding: 10px;">' . $i . '</span>';
            } else {
              $pagination_url = '?page=rekap-chat-form&paged=' . $i;
              if (!empty($selected_greeting)) {
                $pagination_url .= '&filter_greeting=' . urlencode($selected_greeting);
              }
              echo '<a class="page-numbers" style="padding: 10px;" href="' . esc_url($pagination_url) . '">' . $i . '</a>';
            }
            $last = $i;
          }
          ?>
        </div>
      </div>
    <?php endif; ?>

    <script>
      function openModal() {
        document.getElementById('modalForm').style.display = 'block';
      }

      function closeModal() {
        document.getElementById('modalForm').style.display = 'none';
      }

      function clearFilter() {
        // Reset ke halaman utama tanpa filter
        window.location.href = '?page=rekap-chat-form';
      }
      document.getElementById('select_all').addEventListener('click', function() {
        let checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
        checkboxes.forEach(cb => cb.checked = this.checked);
      });
    </script>

    <script>
      jQuery(document).ready(function($) {
        $('#cek-jenis-website').on('click', function(e) {
          e.preventDefault();
          let $this = $(this);
          // loading spin icon
          $this.html('Memproses...');
          let selectedIds = [];
          $('input[name="selected_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
          });

          if (selectedIds.length === 0) {
            alert('Pilih setidaknya satu data!');
            return;
          }

          $('#ai-validation-result').html('Memproses...');

          $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
              action: 'cek_jenis_website_ai',
              ids: selectedIds,
              _wpnonce: '<?php echo wp_create_nonce("cek_jenis_website_ai_nonce"); ?>'
            },
            success: function(response) {
              $('#ai-validation-result').html(response);
              // ganti spiner kembali
              $this.html('Cek Jenis Website');

              if (response && typeof response === 'string') {
                // Ambil data ID dan hasil dari response HTML sederhana
                let parser = new DOMParser();
                let htmlDoc = parser.parseFromString(response, 'text/html');
                let items = htmlDoc.querySelectorAll('li');

                items.forEach(item => {
                  let match = item.innerText.match(/ID (\d+):\s+(✅|❌|⚠️|❓)/);
                  if (match) {
                    let id = match[1];
                    let icon = match[2];

                    let target = document.querySelector('span.ai-result[data-id="' + id + '"]');
                    if (target) {
                      target.innerHTML = icon;
                    }
                  }
                });
              }
            },
            error: function() {
              $('#ai-validation-result').html('<div class="error">Terjadi kesalahan saat memproses permintaan.</div>');
            }
          });
        });
      });
    </script>

  </div>
<?php
}


function show_valid_wa_icon($number)
{
  // Bersihkan input dari spasi, titik, dash
  $number = preg_replace('/[\s\.\-]/', '', $number);
  // jika di awali 62
  $number = preg_replace('/^62/', '0', $number);
  // Validasi: harus mulai dengan 08 dan panjang 10–14 digit
  if (preg_match('/^08[0-9]{8,12}$/', $number)) {
    return '<span >✅</span>'; // HTML icon centang
  } else {
    return '<span >❌</span>'; // HTML icon x
  }

  return '';
}

function format_ai_result($status)
{
  $status = strtolower(trim($status));
  if ($status === 'valid') {
    return '<span class="ai-status valid" style="color:green;">✅</span>';
  } elseif ($status === 'dilarang') {
    return '<span class="ai-status dilarang" style="color:red;">⚠️</span>';
  } elseif ($status) {
    return '<span class="ai-status unknown" style="color:gray;">❓</span>';
  }
  return '';
}
