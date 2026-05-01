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
