(function () {
    'use strict';

    var simButton = document.getElementById('phenixgsm-sim-codes-toggle');
    var simContent = document.getElementById('phenixgsm-sim-codes-content');
    if (simButton && simContent) {
        simButton.addEventListener('click', function () {
            var isHiding = !simContent.hidden;
            simContent.hidden = isHiding;
            simButton.setAttribute('aria-expanded', isHiding ? 'false' : 'true');
            simButton.textContent = isHiding ? 'Afficher mes codes SIM' : 'Masquer mes codes SIM';
        });
    }

    var button = document.getElementById('phenixgsm-esim-toggle');
    var content = document.getElementById('phenixgsm-esim-content');
    var qrTarget = document.getElementById('phenixgsm-esim-qr');
    if (!button || !content || !qrTarget) {
        return;
    }

    var generated = false;
    function generateQr() {
        if (generated) {
            return;
        }

        var activationCode = content.getAttribute('data-activation-code') || '';
        var lpa = document.getElementById('phenixgsm-esim-lpa');
        var confirmation = document.getElementById('phenixgsm-esim-confirmation');
        var error = document.getElementById('phenixgsm-esim-qr-error');
        if (lpa) {
            lpa.textContent = activationCode;
        }
        if (confirmation) {
            confirmation.textContent = content.getAttribute('data-confirmation-code') || '';
        }

        if (typeof QRCode === 'undefined' || activationCode === '') {
            if (error) {
                error.hidden = false;
            }
            return;
        }

        try {
            new QRCode(qrTarget, {
                text: activationCode,
                width: 256,
                height: 256,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
            generated = true;
        } catch (e) {
            if (error) {
                error.hidden = false;
            }
        }
    }

    button.addEventListener('click', function () {
        var isHiding = !content.hidden;
        content.hidden = isHiding;
        button.setAttribute('aria-expanded', isHiding ? 'false' : 'true');
        button.textContent = isHiding ? 'Afficher le QR code eSIM' : 'Masquer le QR code eSIM';
        if (!isHiding) {
            generateQr();
        }
    });

    // The initial request only loads eSIM data after the client pressed the
    // reveal form. Render the QR immediately on that response.
    if (!content.hidden) {
        generateQr();
    }
}());
