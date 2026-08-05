/* Dropzone de fotos compartilhado: arrastar/soltar, galeria e câmera (webcam no notebook, app de câmera no celular) */
(function () {
    if (typeof window.dropzoneFotoPronto !== 'undefined') {
        return;
    }
    window.dropzoneFotoPronto = true;

    var streamAtivo = null;
    var inputAtivo = null;

    function adicionarArquivosAoInput(input, arquivos) {
        if (!window.DataTransfer || !input) {
            return false;
        }
        var dt = new DataTransfer();
        var existentes = input.files ? Array.prototype.slice.call(input.files) : [];
        existentes.forEach(function (f) { dt.items.add(f); });
        Array.prototype.slice.call(arquivos).forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
        return true;
    }

    function pararStream() {
        if (streamAtivo) {
            streamAtivo.getTracks().forEach(function (t) { t.stop(); });
            streamAtivo = null;
        }
    }

    function montarModalCamera() {
        if (document.getElementById('modal_camera_foto')) {
            return;
        }
        var html =
            '<div class="modal fade" id="modal_camera_foto" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 11000;">' +
            '  <div class="modal-dialog" style="max-width: 420px;">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>' +
            '        <h4 class="modal-title"><i class="fa fa-camera"></i> Tirar Foto com a Câmera</h4>' +
            '      </div>' +
            '      <div class="modal-body" style="text-align: center;">' +
            '        <video id="camera_video_preview" autoplay playsinline muted style="width: 100%; max-height: 360px; border-radius: 6px; background: #000;"></video>' +
            '        <p class="text-muted" style="margin-top: 8px; margin-bottom: 0;">Aponte a câmera para o item e clique em "Tirar Foto".</p>' +
            '      </div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>' +
            '        <button type="button" class="btn btn-success" id="btn_capturar_foto"><i class="fa fa-camera"></i> Tirar Foto</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        $('body').append(html);

        $('#modal_camera_foto').on('hidden.bs.modal', function () {
            pararStream();
            var video = document.getElementById('camera_video_preview');
            if (video) {
                video.srcObject = null;
            }
        });

        $('#btn_capturar_foto').off('click').on('click', function () {
            var video = document.getElementById('camera_video_preview');
            if (!video || !video.videoWidth || !video.videoHeight) {
                return;
            }
            var canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(function (blob) {
                if (!blob) {
                    return;
                }
                var nome = 'camera_' + new Date().getTime() + '.jpg';
                var arquivo = new File([blob], nome, { type: 'image/jpeg' });
                if (inputAtivo && adicionarArquivosAoInput(inputAtivo, [arquivo])) {
                    $(inputAtivo).trigger('change');
                }
                $('#modal_camera_foto').modal('hide');
            }, 'image/jpeg', 0.92);
        });
    }

    function abrirCameraWebcam(input) {
        inputAtivo = input;
        montarModalCamera();
        $('#modal_camera_foto').modal('show');
        pararStream();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (s) {
                streamAtivo = s;
                var video = document.getElementById('camera_video_preview');
                if (video) {
                    video.srcObject = s;
                    video.play().catch(function () {});
                }
            })
            .catch(function () {
                $('#modal_camera_foto').modal('hide');
                alert('Não foi possível acessar a câmera. Verifique a permissão no navegador ou use o seletor de arquivos.');
                var cameraInput = input.parentNode ? input.parentNode.querySelector('input[data-camera="true"]') : null;
                if (cameraInput) {
                    cameraInput.click();
                }
            });
    }

    function eDispositivoMovel() {
        if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
            return true;
        }
        return navigator.maxTouchPoints > 1;
    }

    $(document).ready(function () {
        var containers = document.querySelectorAll('[data-dropzone-foto]');
        containers.forEach(function (container) {
            var mainInput = container.querySelector('input[type="file"]:not([data-camera])');
            var cameraInput = container.querySelector('input[type="file"][data-camera]');
            var dropzone = container.querySelector('[data-dropzone-area]');
            var btnGaleria = container.querySelector('[data-galeria]');
            var btnCamera = container.querySelector('[data-camera-btn]');

            if (!mainInput) {
                return;
            }

            if (dropzone) {
                $(dropzone).off('click').on('click', function () {
                    mainInput.click();
                });
                $(dropzone).off('dragover').on('dragover', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropzone).css('border-color', '#337ab7').css('background', '#eef4fb');
                });
                $(dropzone).off('dragleave').on('dragleave', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropzone).css('border-color', '#b0b9c4').css('background', '#f8fafc');
                });
                $(dropzone).off('drop').on('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(dropzone).css('border-color', '#b0b9c4').css('background', '#f8fafc');
                    var arquivos = e.originalEvent.dataTransfer ? e.originalEvent.dataTransfer.files : null;
                    if (!arquivos || arquivos.length === 0) {
                        return;
                    }
                    if (adicionarArquivosAoInput(mainInput, arquivos)) {
                        $(mainInput).trigger('change');
                    } else {
                        alert('Seu navegador não suporta arrastar arquivos. Use os botões abaixo.');
                    }
                });
            }

            if (btnGaleria) {
                $(btnGaleria).off('click').on('click', function (e) {
                    e.stopPropagation();
                    mainInput.click();
                });
            }

            if (btnCamera) {
                $(btnCamera).off('click').on('click', function (e) {
                    e.stopPropagation();
                    if (eDispositivoMovel() || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        if (cameraInput) {
                            cameraInput.click();
                        }
                        return;
                    }
                    abrirCameraWebcam(mainInput);
                });
            }
        });
    });
})();
