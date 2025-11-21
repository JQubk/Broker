<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$context = \Bitrix\Main\Context::getCurrent();
$request = $context->getRequest();
$isSidePanel = ($request->get('IFRAME') === 'Y');
?>
                                </div> <!-- #workarea-inner -->
                            </div> <!-- #workarea -->
                        </div> <!-- #workarea-wrapper -->
                    </div> <!-- #content -->
                </div> <!-- #content-wrapper -->
            </div> <!-- .row -->
    </main>
    <p class="text-white">Etblyattohdiginde</p>
    <?php if (!$isSidePanel): ?>
        <footer id="footer" class="bg-light border-top mt-auto">
            <div class="container-fluid py-3">
                <div class="row">
                    <div class="col-md-8">
                        <div id="copyright">
                            <?php
                            $APPLICATION->IncludeFile(
                                SITE_DIR . "include/copyright.php",
                                [],
                                ["MODE" => "html"]
                            );
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php
                        $APPLICATION->IncludeComponent(
                            "bitrix:menu",
                            "bottom",
                            [
                                "ROOT_MENU_TYPE"        => "bottom",
                                "MENU_CACHE_TYPE"       => "Y",
                                "MENU_CACHE_TIME"       => "36000000",
                                "MENU_CACHE_USE_GROUPS" => "Y",
                                "MENU_CACHE_GET_VARS"   => [],
                                "MAX_LEVEL"             => "1",
                                "CHILD_MENU_TYPE"       => "bottom",
                                "USE_EXT"               => "N",
                                "ALLOW_MULTI_SELECT"    => "N",
                            ],
                            false
                        );
                        ?>
                    </div>
                </div>
            </div>
        </footer>
    <?php endif; ?>

</div> <!-- #page-wrapper -->
</body>
</html>
