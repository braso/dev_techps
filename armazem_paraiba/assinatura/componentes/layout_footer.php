        </div>
    </main>

    <footer class="bg-white border-t border-gray-200 mt-auto">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <img src="<?php echo isset($path_prefix) ? $path_prefix : ''; ?>assets/logo.png" alt="TechPS" class="h-6 grayscale opacity-50">
                    <span class="text-sm text-gray-500">&copy; <?php echo date('Y'); ?> TechPS. Todos os direitos reservados.</span>
                </div>
            
            </div>
        </div>
    </footer>
    
    <script>
        function abrirInstrucoesITI() {
            Swal.fire({
                title: 'Como validar sua assinatura no ITI',
                html: `
                    <div class="text-left text-sm text-gray-700 space-y-3">
                        <p>Siga os passos abaixo para verificar a validade jurídica do documento assinado:</p>
                        <ol class="list-decimal list-inside space-y-2">
                            <li>Ao acessar o site do ITI, clique em <strong>"Escolher arquivo"</strong>.</li>
                            <li>Selecione o <strong>PDF assinado</strong> que possui o certificado ICP-Brasil.</li>
                            <li>Marque a opção <strong>"Concordo com os termos"</strong>.</li>
                            <li>Clique no botão <strong>"Validar"</strong>.</li>
                            <li>Na tela de resultado, clique em <strong>"Entendi"</strong> para continuar.</li>
                        </ol>
                        <p class="text-xs text-gray-500 mt-2">Você será redirecionado para o validador oficial do ITI em uma nova aba.</p>
                    </div>
                `,
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-external-link-alt mr-1"></i> Entendi, abrir ITI',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#4b6cb7',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('https://validar.iti.gov.br', '_blank', 'noopener,noreferrer');
                }
            });
        }

        (function() {
            const menuBtn = document.querySelector('button.md\\:hidden');
            if (menuBtn) {
                menuBtn.addEventListener('click', function() {
                    const menu = document.getElementById('mobile-menu');
                    if (menu) menu.classList.toggle('hidden');
                });
            }

            const toggles = document.querySelectorAll('[data-mobile-toggle]');
            toggles.forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = btn.getAttribute('data-mobile-toggle');
                    if (!id) return;
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.classList.toggle('hidden');
                });
            });
        })();
    </script>
</body>
</html>
