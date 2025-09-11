<?php
global $wpdb;
$table_name = $wpdb->prefix . GREETING_ADS_TABLE;

// Handle impor CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_csv'])) {
  greeting_ads_import_csv();
}

// Handle tambah/edit/hapus data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['add_data'])) {
    $data = array(
      'kata_kunci' => sanitize_text_field($_POST['kata_kunci']),
      'grup_iklan' => sanitize_text_field($_POST['grup_iklan']),
      'id_grup_iklan' => sanitize_text_field($_POST['id_grup_iklan']),
      'nomor_kata_kunci' => sanitize_text_field($_POST['nomor_kata_kunci']),
      'greeting' => sanitize_text_field($_POST['greeting']),
    );
    greeting_ads_add_data($data);
  } elseif (isset($_POST['update_data'])) {
    $data = array(
      'kata_kunci' => sanitize_text_field($_POST['kata_kunci']),
      'grup_iklan' => sanitize_text_field($_POST['grup_iklan']),
      'id_grup_iklan' => sanitize_text_field($_POST['id_grup_iklan']),
      'nomor_kata_kunci' => sanitize_text_field($_POST['nomor_kata_kunci']),
      'greeting' => sanitize_text_field($_POST['greeting']),
    );
    greeting_ads_update_data($_POST['id'], $data);
  }
  $edit_id = isset($_GET['edit']) ? $_GET['edit'] : '';
}

// Ambil semua data dari database
$per_page = 100; // Jumlah baris per halaman
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1; // Halaman aktif
$offset = ($current_page - 1) * $per_page;

// Total jumlah data
$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

// Data untuk halaman saat ini
$search_kata_kunci = isset($_GET['search_kata_kunci']) ? sanitize_text_field($_GET['search_kata_kunci']) : '';
$search_nomor_kata_kunci = isset($_GET['search_nomor_kata_kunci']) ? sanitize_text_field($_GET['search_nomor_kata_kunci']) : '';
$search_greeting = isset($_GET['search_greeting']) ? sanitize_text_field($_GET['search_greeting']) : '';
$where_clauses = [];
$params = [];

if (!empty($search_kata_kunci)) {
  $where_clauses[] = "kata_kunci LIKE %s";
  $params[] = '%' . $wpdb->esc_like($search_kata_kunci) . '%';
}
if (!empty($search_nomor_kata_kunci)) {
  $where_clauses[] = "nomor_kata_kunci LIKE %s";
  $params[] = '%' . $wpdb->esc_like($search_nomor_kata_kunci) . '%';
}
if (!empty($search_greeting)) {
  $where_clauses[] = "greeting LIKE %s";
  $params[] = '%' . $wpdb->esc_like($search_greeting) . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
  $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

$total_items_query = $wpdb->prepare("SELECT COUNT(*) FROM $table_name $where_sql", ...$params);
$total_items = $wpdb->get_var($total_items_query);


$params[] = $per_page;
$params[] = $offset;
$query = "SELECT * FROM $table_name $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";

$data = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

// Untuk edit, cari data berdasarkan ID
$edit_data = null;
if (isset($_GET['edit'])) {
  $edit_id = intval($_GET['edit']); // Pastikan ID adalah integer
  foreach ($data as $row) {
    if ($row['id'] == $edit_id) {
      $edit_data = $row;
      break;
    }
  }
}
?>

<div class="wrap">

  <div class="card" style="max-width: 100% !important;">
    <div class="ga-search-header">
      <h2 class="title">Pencarian Greeting</h2>
      <p class="ga-subtitle">Temukan pesan greeting berdasarkan parameter UTM</p>
    </div>
    <form id="search-form" class="ga-search-form">
      <div class="ga-search-grid">
        <div class="ga-form-group mb-0">
          <label for="search_id_grup_iklan" class="ga-label">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
              <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
            </svg>
            utm_content
          </label>
          <input type="text" name="id_grup_iklan" id="search_id_grup_iklan" class="ga-input" placeholder="Masukkan ID grup iklan" required>
        </div>

        <div class="ga-form-group mb-0">
          <label for="search_nomor_kata_kunci" class="ga-label">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="M21 21l-4.35-4.35"></path>
            </svg>
            utm_medium
          </label>
          <input type="text" name="nomor_kata_kunci" id="search_nomor_kata_kunci" class="ga-input" placeholder="Masukkan nomor kata kunci" required>
        </div>

        <div class="ga-search-button-wrapper">
          <button type="submit" class="ga-btn ga-btn-primary ga-search-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="M21 21l-4.35-4.35"></path>
            </svg>
            Cari Greeting
          </button>
        </div>
      </div>
    </form>

    <!-- Output hasil pencarian -->
    <div id="search-result" class="ga-search-result"></div>
  </div>


  <!-- Data Management Section -->
  <div class="card" style="max-width: 100% !important;">
    <div class="ga-data-header">
      <div>
        <h2 class="title">Data Greeting Ads</h2>
        <p class="ga-subtitle">Kelola pesan greeting dan parameter UTM Anda</p>
      </div>
      <div class="ga-header-actions">
        <button id="bulk-delete-btn" class="ga-btn ga-btn-danger">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="3,6 5,6 21,6"></polyline>
            <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2v2"></path>
          </svg>
          Bulk Delete
        </button>
        <button id="import-csv-btn" class="ga-btn ga-btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="17,8 12,3 7,8"></polyline>
            <line x1="12" y1="3" x2="12" y2="15"></line>
          </svg>
          Import CSV
        </button>
        <button id="add-data-btn" class="ga-btn ga-btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 5v14M5 12h14"></path>
          </svg>
          Tambah Data Baru
        </button>
      </div>
    </div>
    <div class="ga-filter-section">
      <form method="get" class="ga-filter-form">
        <input type="hidden" name="page" value="greeting-ads">
        <div class="ga-filter-grid">
          <div class="ga-filter-group">
            <input type="text" name="search_kata_kunci" placeholder="Cari kata kunci..." value="<?php echo isset($_GET['search_kata_kunci']) ? esc_attr($_GET['search_kata_kunci']) : ''; ?>" class="ga-filter-input">
          </div>
          <div class="ga-filter-group">
            <input type="text" name="search_nomor_kata_kunci" placeholder="Cari nomor kata kunci..." value="<?php echo isset($_GET['search_nomor_kata_kunci']) ? esc_attr($_GET['search_nomor_kata_kunci']) : ''; ?>" class="ga-filter-input">
          </div>
          <div class="ga-filter-group">
            <input type="text" name="search_greeting" placeholder="Cari greeting..." value="<?php echo isset($_GET['search_greeting']) ? esc_attr($_GET['search_greeting']) : ''; ?>" class="ga-filter-input">
          </div>
          <div class="ga-filter-group">
            <button type="submit" class="ga-btn ga-btn-secondary ga-filter-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46 22,3"></polygon>
              </svg>
              Filter
            </button>
            <?php if (!empty(array_filter([$search_kata_kunci, $search_nomor_kata_kunci, $search_greeting]))): ?>
              <a href="?page=greeting-ads" class="ga-btn ga-btn-ghost ga-clear-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
                Bersihkan
              </a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
    <div class="ga-table-container">
      <table class="ga-table">
      <thead>
        <tr>
          <th class=\"ga-th\">ID</th>
          <th class=\"ga-th\">Kata Kunci</th>
          <th class=\"ga-th\">Grup Iklan</th>
          <th class=\"ga-th\">ID Grup Iklan</th>
          <th class=\"ga-th\">Nomor Kata Kunci</th>
          <th class=\"ga-th\">Pesan Greeting</th>
          <th class=\"ga-th\">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($data)): ?>
          <?php foreach ($data as $row): ?>
            <tr class=\"ga-tr\">
              <td class=\"ga-td\"><?php echo esc_html($row['id']); ?></td>
              <td class=\"ga-td\"><?php echo esc_html($row['kata_kunci']); ?></td>
              <td class=\"ga-td\"><?php echo esc_html($row['grup_iklan']); ?></td>
              <td class=\"ga-td\"><?php echo esc_html($row['id_grup_iklan']); ?></td>
              <td class=\"ga-td\"><?php echo esc_html($row['nomor_kata_kunci']); ?></td>
              <td class=\"ga-td ga-greeting-cell\"><?php echo esc_html($row['greeting']); ?></td>
              <td class="ga-td ga-actions-cell">
                <button class="ga-btn ga-btn-secondary ga-btn-sm edit-data-btn"
                  data-id="<?php echo esc_attr($row['id']); ?>"
                  data-kata-kunci="<?php echo esc_attr($row['kata_kunci']); ?>"
                  data-grup-iklan="<?php echo esc_attr($row['grup_iklan']); ?>"
                  data-id-grup-iklan="<?php echo esc_attr($row['id_grup_iklan']); ?>"
                  data-nomor-kata-kunci="<?php echo esc_attr($row['nomor_kata_kunci']); ?>"
                  data-greeting="<?php echo esc_attr($row['greeting']); ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                  </svg>
                  Edit
                </button>
                <button class="ga-btn ga-btn-danger ga-btn-sm delete-data" data-id="<?php echo esc_attr($row['id']); ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3,6 5,6 21,6"></polyline>
                    <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                  </svg>
                  Hapus
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr class=\"ga-tr\">
            <td colspan=\"7\" class=\"ga-td ga-empty-state\">
              <div class=\"ga-empty-content\">
                <svg width=\"48\" height=\"48\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" class=\"ga-empty-icon\">
                  <circle cx=\"11\" cy=\"11\" r=\"8\"></circle>
                  <path d=\"M21 21l-4.35-4.35\"></path>
                </svg>
                <p>Tidak ada data ditemukan</p>
                <p class=\"ga-empty-subtext\">Mulai dengan menambahkan data greeting atau import dari CSV</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php
    $total_pages = ceil($total_items / $per_page);
    if ($total_pages > 1): ?>
      <nav class="tablenav bottom">
        <div class="tablenav-pages">
          <span class="displaying-num"><?php echo number_format_i18n($total_items); ?> item</span>
          <span class="pagination-links">
            <?php if ($current_page > 1): ?>
              <a class="prev-page button" href="<?php echo add_query_arg(['paged' => ($current_page - 1)]); ?>">« Previous</a>
            <?php endif; ?>

            <span class="paging-input">
              <input type="number" class="current-page" value="<?php echo $current_page; ?>" min="1" max="<?php echo $total_pages; ?>" size="2">
              of <span class="total-pages"><?php echo number_format_i18n($total_pages); ?></span>
            </span>

            <?php if ($current_page < $total_pages): ?>
              <a class="next-page button" href="<?php echo add_query_arg(['paged' => ($current_page + 1)]); ?>">Next »</a>
            <?php endif; ?>
          </span>
        </div>
      </nav>
    <?php endif; ?>
  </div>
</div>

<!-- Modern Modal for Add/Edit Data -->
<div id="data-modal" class="ga-modal">
  <div class="ga-modal-content">
    <div class="ga-modal-header">
      <h3 id="modal-title">Add New Data</h3>
      <button class="ga-modal-close" id="modal-close">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ga-modal-body">
      <form id="data-form">
        <input type="hidden" id="data-id" name="id">

        <div class="ga-form-group">
          <label for="modal-kata-kunci" class="ga-label">Kata Kunci</label>
          <input type="text" id="modal-kata-kunci" name="kata_kunci" class="ga-input" required>
        </div>

        <div class="ga-form-group">
          <label for="modal-grup-iklan" class="ga-label">Grup Iklan</label>
          <input type="text" id="modal-grup-iklan" name="grup_iklan" class="ga-input" required>
        </div>

        <div class="ga-form-group">
          <label for="modal-id-grup-iklan" class="ga-label">ID Grup Iklan</label>
          <input type="text" id="modal-id-grup-iklan" name="id_grup_iklan" class="ga-input" required>
        </div>

        <div class="ga-form-group">
          <label for="modal-nomor-kata-kunci" class="ga-label">Nomor Kata Kunci</label>
          <input type="text" id="modal-nomor-kata-kunci" name="nomor_kata_kunci" class="ga-input" required>
        </div>

        <div class="ga-form-group">
          <label for="modal-greeting" class="ga-label">Pesan Greeting</label>
          <textarea id="modal-greeting" name="greeting" class="ga-textarea" rows="3" required></textarea>
        </div>

        <div class="ga-modal-actions">
          <button type="button" class="ga-btn ga-btn-secondary" id="cancel-btn">Batal</button>
          <button type="submit" class="ga-btn ga-btn-primary" id="submit-btn">
            <span id="submit-text">Simpan</span>
            <div id="submit-loader" class="ga-spinner" style="display: none;"></div>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CSV Import Modal -->
<div id="import-modal" class="ga-modal">
  <div class="ga-modal-content">
    <div class="ga-modal-header">
      <h3 id="import-modal-title">Import Data CSV</h3>
      <button class="ga-modal-close" id="import-modal-close">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ga-modal-body">
      <p class="ga-import-description">Upload file CSV untuk mengimpor data greeting secara massal. Pastikan format file sesuai dengan template yang disediakan.</p>
      
      <form id="import-form" method="post" enctype="multipart/form-data">
        <div class="ga-file-upload-area" id="file-drop-area">
          <div class="ga-file-upload-content">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ga-upload-icon">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
              <polyline points="17,8 12,3 7,8"></polyline>
              <line x1="12" y1="3" x2="12" y2="15"></line>
            </svg>
            <p class="ga-upload-text">Pilih file CSV atau seret dan lepas di sini</p>
            <p class="ga-upload-subtext">Hanya mendukung file .csv</p>
            <div id="file-info" class="ga-file-info" style="display: none;">
              <span id="file-name"></span>
              <span id="file-size"></span>
            </div>
          </div>
          <input type="file" name="csv_file" accept=".csv" required class="ga-file-input" id="csv-file-input">
        </div>
        
        <div class="ga-modal-actions">
          <button type="button" class="ga-btn ga-btn-secondary" id="import-cancel-btn">Batal</button>
          <button type="submit" name="import_csv" class="ga-btn ga-btn-primary" id="import-submit-btn">
            <span id="import-submit-text">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17,8 12,3 7,8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              Import Data
            </span>
            <div id="import-submit-loader" class="ga-spinner" style="display: none;"></div>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bulk Delete Modal -->
<div id="bulk-delete-modal" class="ga-modal">
  <div class="ga-modal-content">
    <div class="ga-modal-header">
      <h3 id="bulk-delete-modal-title">Bulk Delete Berdasarkan Nomor Kata Kunci</h3>
      <button class="ga-modal-close" id="bulk-delete-modal-close">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="ga-modal-body">
      <div class="ga-bulk-delete-warning">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ga-warning-icon">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        <h4>Peringatan!</h4>
        <p>Fitur ini akan menghapus SEMUA data greeting yang memiliki nomor kata kunci yang sama. Tindakan ini tidak dapat dibatalkan.</p>
      </div>
      
      <form id="bulk-delete-form">
        <div class="ga-form-group">
          <label for="bulk-delete-keyword-number" class="ga-label">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="8"></circle>
              <path d="M21 21l-4.35-4.35"></path>
            </svg>
            Nomor Kata Kunci
          </label>
          <textarea id="bulk-delete-keyword-number" name="nomor_kata_kunci" class="ga-textarea" rows="6" placeholder="Masukkan nomor kata kunci (satu per baris)&#10;&#10;Contoh:&#10;183848825646&#10;187830281489&#10;184734032123" required></textarea>
          <p class="ga-input-hint">Masukkan satu nomor kata kunci per baris. Format bisa berupa angka saja atau dengan prefix kwd- (contoh: 123456789 atau kwd-123456789)</p>
        </div>

        <div id="bulk-delete-preview" class="ga-bulk-delete-preview" style="display: none;">
          <div class="ga-preview-header">
            <h4>Preview Data yang Akan Dihapus:</h4>
            <span id="preview-count" class="ga-preview-count">0 data</span>
          </div>
          <div id="preview-loading" class="ga-preview-loading" style="display: none;">
            <div class="ga-spinner"></div>
            <span>Memuat preview...</span>
          </div>
          <div id="preview-content" class="ga-preview-content"></div>
        </div>

        <div class="ga-modal-actions">
          <button type="button" class="ga-btn ga-btn-secondary" id="bulk-delete-cancel-btn">Batal</button>
          <button type="button" class="ga-btn ga-btn-secondary" id="bulk-delete-preview-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Preview Data
          </button>
          <button type="submit" class="ga-btn ga-btn-danger" id="bulk-delete-submit-btn" disabled>
            <span id="bulk-delete-submit-text">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3,6 5,6 21,6"></polyline>
                <path d="M19,6v14a2,2,0,0,1-2,2H7a2,2,0,0,1-2-2V6m3,0V4a2,2,0,0,1,2-2h4a2,2,0,0,1,2,2v2"></path>
              </svg>
              Hapus Data
            </span>
            <div id="bulk-delete-submit-loader" class="ga-spinner" style="display: none;"></div>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  /* Modern SaaS UI Styles */
  .mb-0 {
    margin-bottom: 0 !important;
  }

  .ga-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 10000;
    opacity: 0;
    transition: opacity 0.3s ease;
  }

  .ga-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
  }

  .ga-modal-content {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    transform: scale(0.95);
    transition: transform 0.3s ease;
  }

  .ga-modal.show .ga-modal-content {
    transform: scale(1);
  }

  .ga-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 24px 0 24px;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 24px;
  }

  .ga-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #111827;
  }

  .ga-modal-close {
    background: none;
    border: none;
    padding: 8px;
    border-radius: 6px;
    cursor: pointer;
    color: #6b7280;
    transition: all 0.2s ease;
  }

  .ga-modal-close:hover {
    background: #f3f4f6;
    color: #111827;
  }

  .ga-modal-body {
    padding: 0 24px 24px 24px;
  }

  .ga-form-group {
    margin-bottom: 20px;
  }

  .ga-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
  }

  .ga-input,
  .ga-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: #ffffff;
    height: 44px;
    box-sizing: border-box;
  }

  .ga-input:focus,
  .ga-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .ga-textarea {
    resize: vertical;
    min-height: 80px;
    height: auto;
  }

  .ga-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
  }

  .ga-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    border: none;
    outline: none;
  }

  .ga-btn-sm {
    padding: 6px 12px;
    font-size: 13px;
    gap: 6px;
  }

  .ga-btn-primary {
    background: #3b82f6;
    color: white;
  }

  .ga-btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
  }

  .ga-btn-primary:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  .ga-btn-secondary {
    background: #f8fafc;
    color: #374151;
    border: 1px solid #d1d5db;
  }

  .ga-btn-secondary:hover {
    background: #f1f5f9;
    border-color: #9ca3af;
  }

  .ga-btn-danger {
    background: #ef4444;
    color: white;
  }

  .ga-btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
  }

  .ga-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    to {
      transform: rotate(360deg);
    }
  }

  /* Table action buttons alignment */
  td .ga-btn {
    margin-right: 8px;
    vertical-align: middle;
  }

  td .ga-btn:last-child {
    margin-right: 0;
  }

  /* Ensure action column has proper alignment */
  td:last-child {
    white-space: nowrap;
    vertical-align: middle;
  }

  /* Search UI Styles */
  .ga-search-header {
    margin-bottom: 24px;
  }

  .ga-subtitle {
    color: #6b7280;
    font-size: 14px;
    margin: 8px 0 0 0;
  }

  .ga-search-form {
    margin-bottom: 24px;
  }

  .ga-search-grid {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
  }

  .ga-form-group {
    flex: 1;
    min-width: 200px;
  }

  .ga-search-button-wrapper {
    flex-shrink: 0;
  }

  .ga-search-btn {
    white-space: nowrap;
    height: 44px;
    padding: 12px 20px;
  }

  .ga-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
  }

  .ga-search-result {
    margin-top: 20px;
    padding: 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    display: none;
  }

  .ga-search-result.show {
    display: block;
  }

  .ga-search-result p {
    margin: 0;
    color: #374151;
    font-weight: 500;
  }

  /* Filter UI Styles */
  .ga-filter-section {
    margin-bottom: 20px;
    padding: 20px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
  }

  .ga-filter-form {
    margin: 0;
  }

  .ga-filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 12px;
    align-items: center;
  }

  .ga-filter-group {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .ga-filter-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: #ffffff;
  }

  .ga-filter-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }

  .ga-filter-btn,
  .ga-clear-btn {
    white-space: nowrap;
    padding: 8px 16px;
    font-size: 14px;
  }

  .ga-btn-ghost {
    background: transparent;
    color: #6b7280;
    border: 1px solid #d1d5db;
  }

  .ga-btn-ghost:hover {
    background: #f3f4f6;
    color: #374151;
  }

  /* Section Headers */
  .ga-section-header {
    margin-bottom: 24px;
  }

  .ga-data-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    gap: 20px;
  }

  .ga-header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
  }

  /* CSV Import Styles */
  .ga-import-form {
    margin: 0;
  }

  .ga-file-upload-area {
    position: relative;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    transition: all 0.2s ease;
    background: #fafafa;
    margin-bottom: 20px;
  }

  .ga-file-upload-area:hover {
    border-color: #3b82f6;
    background: #f8fafc;
  }

  .ga-file-upload-area.dragover {
    border-color: #3b82f6;
    background: #eff6ff;
  }

  .ga-file-upload-content {
    pointer-events: none;
  }

  .ga-upload-icon {
    color: #9ca3af;
    margin-bottom: 16px;
  }

  .ga-upload-text {
    font-size: 16px;
    font-weight: 500;
    color: #374151;
    margin: 0 0 8px 0;
  }

  .ga-upload-subtext {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
  }

  .ga-file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }

  .ga-import-actions {
    display: flex;
    justify-content: flex-start;
  }

  .ga-import-description {
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 20px;
    line-height: 1.5;
  }

  .ga-file-info {
    margin-top: 12px;
    padding: 8px 12px;
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
    font-size: 12px;
    color: #0c4a6e;
  }

  .ga-file-info span {
    display: block;
  }

  /* Table Styles */
  .ga-table-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    overflow: hidden;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  }

  .ga-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }

  .ga-th {
    background: #f8fafc;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
  }

  .ga-td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    vertical-align: middle;
  }

  .ga-tr:hover {
    background: #f8fafc;
  }

  .ga-tr:last-child .ga-td {
    border-bottom: none;
  }

  .ga-greeting-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .ga-actions-cell {
    white-space: nowrap;
    width: 1%;
  }

  .ga-empty-state {
    text-align: center;
    padding: 60px 20px !important;
  }

  .ga-empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }

  .ga-empty-icon {
    color: #9ca3af;
  }

  .ga-empty-content p {
    margin: 0;
    color: #6b7280;
  }

  .ga-empty-content p:first-of-type {
    font-weight: 500;
    color: #374151;
    font-size: 16px;
  }

  .ga-empty-subtext {
    font-size: 14px;
  }

  /* Bulk Delete Modal Styles */
  .ga-bulk-delete-warning {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 20px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    margin-bottom: 24px;
  }

  .ga-warning-icon {
    color: #dc2626;
    margin-bottom: 12px;
  }

  .ga-bulk-delete-warning h4 {
    color: #dc2626;
    font-size: 18px;
    font-weight: 600;
    margin: 0 0 8px 0;
  }

  .ga-bulk-delete-warning p {
    color: #991b1b;
    margin: 0;
    line-height: 1.5;
  }

  .ga-input-hint {
    font-size: 12px;
    color: #6b7280;
    margin: 4px 0 0 0;
    font-style: italic;
  }

  .ga-bulk-delete-preview {
    margin-top: 20px;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
  }

  .ga-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  .ga-preview-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
  }

  .ga-preview-count {
    background: #3b82f6;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
  }

  .ga-preview-loading {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 14px;
  }

  .ga-preview-content {
    max-height: 200px;
    overflow-y: auto;
  }

  .ga-preview-item {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 13px;
  }

  .ga-preview-item:last-child {
    margin-bottom: 0;
  }

  .ga-preview-item .ga-preview-id {
    font-weight: 600;
    color: #374151;
  }

  .ga-preview-item .ga-preview-keyword {
    color: #6b7280;
    font-style: italic;
  }

  .ga-preview-item .ga-preview-greeting {
    color: #374151;
    margin-top: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .ga-preview-summary {
    margin-bottom: 16px;
    padding: 12px;
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
  }

  .ga-preview-summary h5 {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    color: #0c4a6e;
  }

  .ga-summary-item {
    padding: 4px 8px;
    margin: 2px 0;
    background: white;
    border-radius: 4px;
    font-size: 13px;
    color: #374151;
  }

  .ga-preview-items h5 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
  }

  /* Responsive adjustments */
  @media (max-width: 1024px) {
    .ga-search-grid {
      grid-template-columns: 1fr;
      gap: 16px;
    }

    .ga-filter-grid {
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .ga-filter-group {
      justify-content: stretch;
    }

    .ga-data-header {
      flex-direction: column;
      align-items: stretch;
    }

    .ga-table-container {
      overflow-x: auto;
    }

    .ga-greeting-cell {
      max-width: 150px;
    }
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('search-form');
    const searchResult = document.getElementById('search-result');

    searchForm.addEventListener('submit', function(e) {
      e.preventDefault();

      // Ambil nilai dari input form
      const idGrupIklan = document.getElementById('search_id_grup_iklan').value;
      const nomorKataKunci = document.getElementById('search_nomor_kata_kunci').value;

      // Kirim data ke server via AJAX
      fetch(ajaxurl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: new URLSearchParams({
            action: 'search_greeting', // Nama action untuk hook AJAX
            id_grup_iklan: idGrupIklan,
            nomor_kata_kunci: nomorKataKunci,
          }),
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Tampilkan hasil pencarian
            searchResult.innerHTML = `<p><strong>Greeting:</strong> ${data.data.greeting}</p>`;
            searchResult.classList.add('show');
          } else {
            searchResult.innerHTML = `<p style="color: #ef4444;">${data.message}</p>`;
            searchResult.classList.add('show');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          searchResult.innerHTML = `<p>Terjadi kesalahan saat mencari data.</p>`;
        });
    });
  });

  // Modal functionality
  const modal = document.getElementById('data-modal');
  const addBtn = document.getElementById('add-data-btn');
  
  // Import modal functionality
  const importModal = document.getElementById('import-modal');
  const importBtn = document.getElementById('import-csv-btn');
  const importForm = document.getElementById('import-form');
  const importCancelBtn = document.getElementById('import-cancel-btn');
  const importModalClose = document.getElementById('import-modal-close');
  const fileDropArea = document.getElementById('file-drop-area');
  const csvFileInput = document.getElementById('csv-file-input');
  const fileInfo = document.getElementById('file-info');
  const fileName = document.getElementById('file-name');
  const fileSize = document.getElementById('file-size');
  const importSubmitBtn = document.getElementById('import-submit-btn');
  const importSubmitText = document.getElementById('import-submit-text');
  const importSubmitLoader = document.getElementById('import-submit-loader');
  const modalTitle = document.getElementById('modal-title');
  const dataForm = document.getElementById('data-form');
  const submitBtn = document.getElementById('submit-btn');
  const submitText = document.getElementById('submit-text');
  const submitLoader = document.getElementById('submit-loader');
  const cancelBtn = document.getElementById('cancel-btn');
  const modalClose = document.getElementById('modal-close');

  // Form fields
  const dataId = document.getElementById('data-id');
  const kataKunci = document.getElementById('modal-kata-kunci');
  const grupIklan = document.getElementById('modal-grup-iklan');
  const idGrupIklan = document.getElementById('modal-id-grup-iklan');
  const nomorKataKunci = document.getElementById('modal-nomor-kata-kunci');
  const greeting = document.getElementById('modal-greeting');

  let isEditMode = false;

  // Open modal for adding new data
  addBtn.addEventListener('click', function() {
    openModal('add');
  });

  // Open import modal
  importBtn.addEventListener('click', function() {
    openImportModal();
  });

  function openImportModal() {
    importModal.style.display = 'flex';
    setTimeout(() => importModal.classList.add('show'), 10);
  }

  function closeImportModal() {
    importModal.classList.remove('show');
    setTimeout(() => {
      importModal.style.display = 'none';
      importForm.reset();
      fileInfo.style.display = 'none';
      fileDropArea.classList.remove('dragover');
      importSubmitBtn.disabled = false;
      importSubmitText.style.display = 'inline-flex';
      importSubmitLoader.style.display = 'none';
    }, 300);
  }

  // Close import modal events
  importModalClose.addEventListener('click', closeImportModal);
  importCancelBtn.addEventListener('click', closeImportModal);
  importModal.addEventListener('click', function(e) {
    if (e.target === importModal) closeImportModal();
  });

  // File upload handling
  csvFileInput.addEventListener('change', function(e) {
    handleFile(e.target.files[0]);
  });

  // Drag and drop functionality
  fileDropArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    fileDropArea.classList.add('dragover');
  });

  fileDropArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    fileDropArea.classList.remove('dragover');
  });

  fileDropArea.addEventListener('drop', function(e) {
    e.preventDefault();
    fileDropArea.classList.remove('dragover');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      csvFileInput.files = files;
      handleFile(files[0]);
    }
  });

  function handleFile(file) {
    if (file && file.type === 'text/csv') {
      fileName.textContent = file.name;
      fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
      fileInfo.style.display = 'block';
    } else {
      alert('Mohon pilih file CSV yang valid.');
      csvFileInput.value = '';
      fileInfo.style.display = 'none';
    }
  }

  // Handle import form submission
  importForm.addEventListener('submit', function(e) {
    if (!csvFileInput.files[0]) {
      e.preventDefault();
      alert('Mohon pilih file CSV terlebih dahulu.');
      return;
    }

    importSubmitBtn.disabled = true;
    importSubmitText.style.display = 'none';
    importSubmitLoader.style.display = 'inline-block';
  });

  // Open modal for editing data
  document.addEventListener('click', function(e) {
    if (e.target.closest('.edit-data-btn')) {
      const btn = e.target.closest('.edit-data-btn');
      const data = {
        id: btn.getAttribute('data-id'),
        kata_kunci: btn.getAttribute('data-kata-kunci'),
        grup_iklan: btn.getAttribute('data-grup-iklan'),
        id_grup_iklan: btn.getAttribute('data-id-grup-iklan'),
        nomor_kata_kunci: btn.getAttribute('data-nomor-kata-kunci'),
        greeting: btn.getAttribute('data-greeting')
      };
      openModal('edit', data);
    }
  });

  function openModal(mode, data = null) {
    isEditMode = mode === 'edit';
    modalTitle.textContent = isEditMode ? 'Edit Data' : 'Tambah Data Baru';
    submitText.textContent = isEditMode ? 'Perbarui' : 'Simpan';

    if (isEditMode && data) {
      dataId.value = data.id;
      kataKunci.value = data.kata_kunci;
      grupIklan.value = data.grup_iklan;
      idGrupIklan.value = data.id_grup_iklan;
      nomorKataKunci.value = data.nomor_kata_kunci;
      greeting.value = data.greeting;
    } else {
      dataForm.reset();
      dataId.value = '';
    }

    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
  }

  function closeModal() {
    modal.classList.remove('show');
    setTimeout(() => {
      modal.style.display = 'none';
      dataForm.reset();
      submitBtn.disabled = false;
      submitText.style.display = 'inline';
      submitLoader.style.display = 'none';
    }, 300);
  }

  // Close modal events
  modalClose.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e) {
    if (e.target === modal) closeModal();
  });

  // Handle form submission
  dataForm.addEventListener('submit', function(e) {
    e.preventDefault();

    submitBtn.disabled = true;
    submitText.style.display = 'none';
    submitLoader.style.display = 'inline-block';

    const formData = new FormData(dataForm);
    const action = isEditMode ? 'update_data' : 'add_data';
    formData.append(action, '1');

    fetch('', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (response.ok) {
          closeModal();
          location.reload();
        } else {
          throw new Error('Network response was not ok');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menyimpan data.');
        submitBtn.disabled = false;
        submitText.style.display = 'inline';
        submitLoader.style.display = 'none';
      });
  });

  // Delete functionality with modern confirmation
  document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-data')) {
      e.preventDefault();
      const btn = e.target.closest('.delete-data');
      const id = btn.getAttribute('data-id');

      if (confirm('Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.')) {
        // Show loading state
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<div class="ga-spinner"></div> Menghapus...';

        fetch(ajaxurl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
              action: 'delete_greeting',
              id: id,
            }),
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload();
            } else {
              alert(data.data);
              btn.disabled = false;
              btn.innerHTML = originalText;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menghapus data.');
            btn.disabled = false;
            btn.innerHTML = originalText;
          });
      }
    }
  });

  // Bulk Delete Modal Functionality
  const bulkDeleteModal = document.getElementById('bulk-delete-modal');
  const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
  const bulkDeleteForm = document.getElementById('bulk-delete-form');
  const bulkDeleteModalClose = document.getElementById('bulk-delete-modal-close');
  const bulkDeleteCancelBtn = document.getElementById('bulk-delete-cancel-btn');
  const bulkDeletePreviewBtn = document.getElementById('bulk-delete-preview-btn');
  const bulkDeleteSubmitBtn = document.getElementById('bulk-delete-submit-btn');
  const bulkDeleteKeywordNumber = document.getElementById('bulk-delete-keyword-number');
  const bulkDeletePreview = document.getElementById('bulk-delete-preview');
  const previewLoading = document.getElementById('preview-loading');
  const previewContent = document.getElementById('preview-content');
  const previewCount = document.getElementById('preview-count');
  const bulkDeleteSubmitText = document.getElementById('bulk-delete-submit-text');
  const bulkDeleteSubmitLoader = document.getElementById('bulk-delete-submit-loader');

  // Open bulk delete modal
  bulkDeleteBtn.addEventListener('click', function() {
    openBulkDeleteModal();
  });

  function openBulkDeleteModal() {
    bulkDeleteModal.style.display = 'flex';
    setTimeout(() => bulkDeleteModal.classList.add('show'), 10);
  }

  function closeBulkDeleteModal() {
    bulkDeleteModal.classList.remove('show');
    setTimeout(() => {
      bulkDeleteModal.style.display = 'none';
      bulkDeleteForm.reset();
      bulkDeletePreview.style.display = 'none';
      bulkDeleteSubmitBtn.disabled = true;
      previewContent.innerHTML = '';
      bulkDeleteSubmitBtn.disabled = false;
      bulkDeleteSubmitText.style.display = 'inline-flex';
      bulkDeleteSubmitLoader.style.display = 'none';
    }, 300);
  }

  // Close bulk delete modal events
  bulkDeleteModalClose.addEventListener('click', closeBulkDeleteModal);
  bulkDeleteCancelBtn.addEventListener('click', closeBulkDeleteModal);
  bulkDeleteModal.addEventListener('click', function(e) {
    if (e.target === bulkDeleteModal) closeBulkDeleteModal();
  });

  // Preview data functionality
  bulkDeletePreviewBtn.addEventListener('click', function() {
    const keywordNumbers = bulkDeleteKeywordNumber.value.trim();
    if (!keywordNumbers) {
      alert('Mohon masukkan nomor kata kunci terlebih dahulu.');
      return;
    }

    // Parse multiple keyword numbers from textarea
    const keywordLines = keywordNumbers.split('\n')
      .map(line => line.trim())
      .filter(line => line.length > 0)
      .map(line => line.replace(/^kwd-/, '')); // Clean kwd- prefix

    if (keywordLines.length === 0) {
      alert('Mohon masukkan nomor kata kunci yang valid.');
      return;
    }

    bulkDeletePreview.style.display = 'block';
    previewLoading.style.display = 'flex';
    previewContent.innerHTML = '';
    bulkDeleteSubmitBtn.disabled = true;

    // Fetch preview data
    const formData = new FormData();
    formData.append('action', 'preview_bulk_delete_greeting');
    formData.append('nomor_kata_kunci', JSON.stringify(keywordLines));

    fetch(ajaxurl, {
      method: 'POST',
      body: formData,
    })
    .then(response => response.json())
    .then(data => {
      previewLoading.style.display = 'none';
      
      if (data.success) {
        const items = data.data.items;
        const count = data.data.count;
        const summary = data.data.summary;
        
        previewCount.textContent = `${count} data total`;
        
        if (items.length > 0) {
          let summaryHtml = '';
          if (summary && summary.length > 0) {
            summaryHtml = `
              <div class="ga-preview-summary">
                <h5>Ringkasan per nomor kata kunci:</h5>
                ${summary.map(s => `<div class="ga-summary-item">${s.keyword}: ${s.count} data</div>`).join('')}
              </div>
            `;
          }
          
          previewContent.innerHTML = summaryHtml + `
            <div class="ga-preview-items">
              <h5>Sample data yang akan dihapus (max 20 item):</h5>
              ${items.map(item => `
                <div class="ga-preview-item">
                  <div class="ga-preview-id">ID: ${item.id} | Nomor: ${item.nomor_kata_kunci}</div>
                  <div class="ga-preview-keyword">${item.kata_kunci} (${item.grup_iklan})</div>
                  <div class="ga-preview-greeting">${item.greeting}</div>
                </div>
              `).join('')}
            </div>
          `;
          bulkDeleteSubmitBtn.disabled = false;
        } else {
          previewContent.innerHTML = '<p style="text-align: center; color: #6b7280; margin: 20px 0;">Tidak ada data ditemukan dengan nomor kata kunci tersebut.</p>';
        }
      } else {
        previewContent.innerHTML = `<p style="text-align: center; color: #dc2626; margin: 20px 0;">${data.data}</p>`;
      }
    })
    .catch(error => {
      console.error('Error:', error);
      previewLoading.style.display = 'none';
      previewContent.innerHTML = '<p style="text-align: center; color: #dc2626; margin: 20px 0;">Terjadi kesalahan saat memuat preview.</p>';
    });
  });

  // Handle bulk delete form submission
  bulkDeleteForm.addEventListener('submit', function(e) {
    e.preventDefault();

    const keywordNumbers = bulkDeleteKeywordNumber.value.trim();
    if (!keywordNumbers) {
      alert('Mohon masukkan nomor kata kunci terlebih dahulu.');
      return;
    }

    // Parse multiple keyword numbers from textarea
    const keywordLines = keywordNumbers.split('\n')
      .map(line => line.trim())
      .filter(line => line.length > 0)
      .map(line => line.replace(/^kwd-/, '')); // Clean kwd- prefix

    if (keywordLines.length === 0) {
      alert('Mohon masukkan nomor kata kunci yang valid.');
      return;
    }

    const keywordCount = keywordLines.length;
    if (!confirm(`Apakah Anda yakin ingin menghapus semua data dengan ${keywordCount} nomor kata kunci ini? Tindakan ini tidak dapat dibatalkan.`)) {
      return;
    }

    bulkDeleteSubmitBtn.disabled = true;
    bulkDeleteSubmitText.style.display = 'none';
    bulkDeleteSubmitLoader.style.display = 'inline-block';

    const formData = new FormData();
    formData.append('action', 'bulk_delete_greeting_by_keyword');
    formData.append('nomor_kata_kunci', JSON.stringify(keywordLines));

    fetch(ajaxurl, {
      method: 'POST',
      body: formData,
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert(data.data);
        closeBulkDeleteModal();
        location.reload();
      } else {
        alert(data.data);
        bulkDeleteSubmitBtn.disabled = false;
        bulkDeleteSubmitText.style.display = 'inline-flex';
        bulkDeleteSubmitLoader.style.display = 'none';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Terjadi kesalahan saat menghapus data.');
      bulkDeleteSubmitBtn.disabled = false;
      bulkDeleteSubmitText.style.display = 'inline-flex';
      bulkDeleteSubmitLoader.style.display = 'none';
    });
  });
</script>