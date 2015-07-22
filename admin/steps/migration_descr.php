<?php

if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST["step_code"] == "migration_descr" && check_bitrix_sessid('send_sessid')) {
    require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_js.php");

    $version = isset($_POST['version']) ? $_POST['version'] : 0;
    $descr = $manager->getDescription($version, GetMessage('SPRINT_MIGRATION_EMPTY_DESCR'));

    $existsfile = $manager->getExistsVersionName($version);

    $webdir = \Sprint\Migration\Env::getMigrationWebDir();

    ?>
    <div class="c-migration-descr">
        <?= $descr ?>

        <?if ($webdir && $existsfile):?>
            <br/>
            <? $href = '/bitrix/admin/fileman_file_view.php?' . http_build_query(array(
                    'lang' => LANGUAGE_ID,
                    'site' => SITE_ID,
                    'path' => $webdir . '/' . $version . '.php'
                ))?>
            <a href="<?=$href?>" target="_blank" title=""><?=GetMessage('SPRINT_MIGRATION_VIEW')?></a>
        <?endif?>
    </div>
    <?
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin_js.php");
    die();
}