(function () {
    const modulo = document.body.dataset.helpModulo || '';
    const completadosRaw = document.body.dataset.helpCompletados || '[]';
    const csrfToken = document.body.dataset.helpCsrf || '';

    let completados = [];
    try {
        completados = JSON.parse(completadosRaw);
    } catch (_) {
        completados = [];
    }

    window.helpConfig = {
        modulo,
        completados,
        csrfToken,
    };
})();
