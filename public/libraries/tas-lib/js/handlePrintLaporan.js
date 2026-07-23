var vars = 0
window.addEventListener("message", function (event) {
    if (event.data === "printAction") {
        printDocument()

    }
    if (event.data === "escapeAction") {
        closeModal()
        // Lakukan sesuatu di sini, misalnya trigger download
    }
});
window.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'p') {
        const modal = $('#printModal')
        console.log(modal.is(':visible'), modal.length);
        if ( modal.is(':visible') && (!modal || modal.length > 0)) {
            console.log('Modal is open, calling printDocument');
            e.preventDefault(); // Mencegah print default browser
            printDocument();
        }
    }
});
function closeModal() {
    console.log('closeModal called');
    const modal = $('#printModal')
    if (modal) {
        modal.modal('hide')
        // Hapus konten iframe jika ada
        const iframe = document.getElementById('pdf-viewer');
        if (iframe) {
            iframe.src = '';
        }
    }
}
function printDocument() {
// Misalnya cetak iframe khusus
  const iframe = document.getElementById('pdf-viewer');
  console.log('printDocument called');

  if (iframe && iframe.contentWindow && iframe.contentWindow.print) {
      // iframe.contentWindow.focus();
      document.getElementById('pdf-print').contentWindow.print();
      vars++

  };
}
