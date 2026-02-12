<?php
function tampilan_baru()
{
  ob_start();
?>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: sans-serif;
      background: #f4f4f4;
    }

    .wa-float {
      position: fixed;
      bottom: 16px;
      right: 16px;
      z-index: 9999;
      display: flex;
      align-items: center;
      gap: 12px;
      background-color: #25d366;
      color: white;
      font-family: sans-serif;
      font-size: 20px;
      font-weight: 600;
      padding: 10px 24px;
      border-radius: 40px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
      cursor: pointer;
      text-decoration: none;
      animation: smooth-bounce 2s infinite;
      overflow: hidden;
      transition: transform 0.2s ease;
    }

    .wa-float svg {
      width: 26px;
      height: 26px;
      fill: white;
      flex-shrink: 0;
      position: relative;
      z-index: 2;
    }

    .wa-float .ripple {
      position: absolute;
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      z-index: 1;
      animation: ripple 1.8s infinite ease-out;
    }

    @keyframes ripple {
      0% {
        transform: scale(0.3);
        opacity: 0.6;
      }

      70% {
        transform: scale(1.5);
        opacity: 0;
      }

      100% {
        transform: scale(1.5);
        opacity: 0;
      }
    }

    @keyframes smooth-bounce {

      0%,
      100% {
        transform: translateY(0);
        animation-timing-function: ease-in-out;
      }

      30% {
        transform: translateY(-8px);
        animation-timing-function: ease-in;
      }

      50% {
        transform: translateY(0);
        animation-timing-function: ease-out;
      }

      70% {
        transform: translateY(-4px);
        animation-timing-function: ease-in;
      }

      90% {
        transform: translateY(0);
      }
    }

    @keyframes indlep {
      0% {
        transform: scale(1);
      }

      20% {
        transform: scale(1.1) rotate(2deg);
      }

      40% {
        transform: scale(0.95) rotate(-2deg);
      }

      60% {
        transform: scale(1.05) rotate(1deg);
      }

      80% {
        transform: scale(0.97) rotate(-1deg);
      }

      100% {
        transform: scale(1) rotate(0);
      }
    }

    .wa-float.indlep {
      animation: indlep 0.4s ease;
    }

    .form-wrapper {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      background-color: rgba(0, 0, 0, 0.9);
      z-index: 9999;
      padding: 0 16px;
      overflow: visible;
      padding-top: 40px;
    }

    .form-wrapper.show {
      display: flex;
      animation: fadeInUp 0.8s ease forwards;
    }

    .form-wrapper.hide {
      animation: fadeOutDown 0.8s ease forwards;
    }

    .form-container {
      background: #fff;
      padding: 24px 20px;
      border-radius: 16px;
      width: 100%;
      max-width: 360px;
      max-height: 90vh;
      overflow: visible;
      position: relative;
    }

    .form-container label {
      display: block;
      margin: 10px 0 6px;
      font-size: 16px;
      font-family: 'Lato', sans-serif;
    }

    .form-container input,
    .form-container textarea {
      width: 100%;
      padding: 10px 8px 10px 12px;
      border: 1px solid #ccc;
      border-radius: 8px;
      font-size: 16px;
      font-family: 'Lato', sans-serif;
      margin-bottom: 12px;
    }

    .form-container button {
      width: 100%;
      background-color: #25d366;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 8px;
      font-weight: bold;
      font-size: 18px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .form-container button:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      background-color: #ccc;
    }

    .form-container button.disable {
      background-color: #999;
    }

    .spinner {
      display: inline-block;
      width: 18px;
      height: 18px;
      border: 3px solid white;
      border-radius: 50%;
      border-top-color: transparent;
      animation: spin 0.8s linear infinite;
      vertical-align: middle;
      margin-right: 8px;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(50px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeOutDown {
      from {
        opacity: 1;
        transform: translateY(0);
      }

      to {
        opacity: 0;
        transform: translateY(50px);
      }
    }

    @keyframes shake {
      0% {
        transform: translateX(0);
      }

      25% {
        transform: translateX(-5px);
      }

      50% {
        transform: translateX(5px);
      }

      75% {
        transform: translateX(-5px);
      }

      100% {
        transform: translateX(0);
      }
    }

    .shake {
      animation: shake 0.4s ease;
    }

    @keyframes popScale {
      0% {
        transform: scale(1);
      }

      50% {
        transform: scale(1.1);
      }

      100% {
        transform: scale(1);
      }
    }

    .pop-scale {
      animation: popScale 0.3s ease;
    }

    .close-btn {
      position: absolute;
      top: -20px;
      left: 50%;
      transform: translateX(-50%);
      background-color: grey;
      color: white;
      font-size: 32px;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      text-align: center;
      line-height: 40px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      z-index: 10;
    }
  </style>
  <a href="https://velocitydeveloper.com/form-chat/" class="wa-float">
    <span class="ripple"></span>
    <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
      <path
        d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.6 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.557 6.557 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.729.729 0 0 0-.529.247c-.182.198-.691.677-.691 1.654 0 .977.71 1.916.81 2.049.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z" />
    </svg>
    WhatsApp
  </a>

  <div class="form-wrapper" id="formWrapper">
    <div class="form-container">
      <div class="close-btn" onclick="toggleForm()">×</div>
      <div style="height:100%;max-height:70vh;width:100%;overflow-y:auto;">
        <?php echo do_shortcode('[chat-form-input]'); ?>
      </div>
    </div>
  </div>

  <script>
    let originalBtnHTML;
    document.addEventListener("DOMContentLoaded", function() {
      originalBtnHTML = document.getElementById("submitBtn").innerHTML;
    });

    function triggerIndlep() {
      const el = document.querySelector('.wa-float');
      el.classList.remove('indlep');
      void el.offsetWidth;
      el.classList.add('indlep');
    }

    function toggleForm() {
      const wrapper = document.getElementById("formWrapper");
      if (wrapper.classList.contains("show")) {
        wrapper.classList.remove("show");
        wrapper.classList.add("hide");
        setTimeout(() => {
          wrapper.classList.remove("hide");
          wrapper.style.display = "none";
        }, 800);
      } else {
        wrapper.classList.remove("hide");
        wrapper.classList.add("show");
        wrapper.style.display = "flex";
      }
    }

    function kirimKeWA() {

      const nama = document.getElementById("nama").value.trim();
      const noWa = document.getElementById("no_wa").value.trim();
      const jenisWeb = document.getElementById("jenis_web").value.trim();
      const button = document.getElementById("submitBtn");
      const formContainer = document.querySelector(".form-container");

      if (!nama || !noWa || !jenisWeb) {
        formContainer.classList.remove("shake");
        void formContainer.offsetWidth;
        formContainer.classList.add("shake");
        return;
      }

      const pesan = `Halo, saya ${nama}. No WA saya ${noWa}. Saya ingin membuat website: ${jenisWeb}`;
      const link = `https://wa.me/62XXXXXXXXXX?text=${encodeURIComponent(pesan)}`;

      button.disabled = true;
      button.innerHTML = `<span class="spinner"></span>Mengirim...`;
      button.classList.remove("pop-scale");
      void button.offsetWidth;
      button.classList.add("pop-scale");

      setTimeout(() => {
        window.open(link, "_blank");
        setTimeout(() => {
          button.disabled = false;
          button.innerHTML = originalBtnHTML;
        }, 1000);
      }, 300);
    }
  </script>


  <script>
    // Scroll otomatis saat input di-focus, supaya tidak tertutup keyboard
    document.querySelectorAll('input, textarea').forEach(function(el) {
      el.addEventListener('focus', function() {
        setTimeout(() => {
          el.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
          });
        }, 300);
      });
    });
  </script>
<?php
  return ob_get_clean();
}
function whatsapp_floating()
{
  if (wp_is_mobile() && !is_page('form-chat') && !is_page('order')) {
    echo tampilan_baru();
  }
}
add_action('wp_footer', 'whatsapp_floating');
