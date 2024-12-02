                                </div>
                            </div>
                        </div>
                        <!-- FIM PAGE CONTENT INNER -->
                    </div>
                </div>
                <!-- FIM PAGE CONTENT BODY -->


                <!-- FIM CONTENT BODY -->
                </div>
                <!-- FIM CONTENT -->
                
            </div>
            <!-- FIM CONTAINER -->

        <!-- INICIO FOOTER -->
        <!-- INICIO INNER FOOTER -->
        <div class="page-footer">
            <div class="container-fluid"> 
                <?php date("Y")?> &copy; <a href="https://www.techps.com.br" target="_blank" style="margin-right: 30px">TechPS</a> Versão: <?= $version?>
            </div>
        </div>
        <div class="scroll-to-top">
            <i class="icon-arrow-up"></i>
        </div>
        <!-- FIM INNER FOOTER -->
        <!-- FIM FOOTER -->
        <!-- INICIO CORE PLUGINS -->

        <form id="loginTimeoutForm" method="post" target="<?=($_SERVER['DOCUMENT_ROOT']).$_ENV["APP_PATH"].$_ENV["CONTEX_PATH"]?>/logout.php" action="logout"></form>
        
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/js.cookie.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-hover-dropdown/bootstrap-hover-dropdown.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/jquery-slimscroll/jquery.slimscroll.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/uniform/jquery.uniform.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-switch/js/bootstrap-switch.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/plugins/bootstrap-tabdrop/js/bootstrap-tabdrop.js" type="text/javascript"></script>
        <!-- FIM CORE PLUGINS -->
        <!-- INICIO TEMA GLOBAL SCRIPTS -->
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/global/scripts/app.min.js" type="text/javascript"></script>
        <!-- FIM TEMA GLOBAL SCRIPTS -->
        <!-- INICIO TEMA LAYOUT SCRIPTS -->
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/scripts/layout.min.js" type="text/javascript"></script>
        <script src="<?=$_ENV["URL_BASE"].$_ENV["APP_PATH"]?>/contex20/assets/layout/scripts/demo.min.js" type="text/javascript"></script>
        <!-- FIM TEMA LAYOUT SCRIPTS -->


        <script>
            var timeoutId;
            function updateTimer(){
                if(timeoutId){
                    clearTimeout(timeoutId);
                }
                timeoutId = setTimeout(function(){
                    let form = document.getElementById('loginTimeoutForm');
                    form.submit();
                    window.location.href = '<?= $CONTEX['path']?>/logout.php';
                }, 15*60*1000);
            }

            document.getElementsByClassName('loading')[0].style.display = 'none';
        </script>
    </body>
</html>