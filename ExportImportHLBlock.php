<?php
/**
 * Created by PhpStorm.
 * User: Alya
 * Date: 07.06.2016
 * Time: 17:18
 */
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php"); ?>
    <h1>Импорт/Экспорт хайлод блоков</h1>

    <section>
        <h2>Экспорт</h2>
        <form action="ExportImportHLBlock.php" method="post" name="export">
            <input type="text" name="exportID" placeholder="Введите id хайлода - и получите ссылку на файлик"
                   style="width: 300px;">
            <input type="submit" value="Go!">
        </form>
    </section>

    <section>
        <h2>Импорт</h2>
        <form action="ExportImportHLBlock.php" method="post" name="import">
            <input type="text" name="importPath" placeholder="Введите ссылку на файл" style="width: 300px;">
            <input type="submit" value="Go!">
        </form>
    </section>
<?

if ($_POST['exportID']) {
    \Bitrix\Main\Loader::IncludeModule('highloadblock');

    $highloadID = intval($_POST['exportID']);

    $rsData = \Bitrix\Highloadblock\HighloadBlockTable::getById($highloadID);
    if ($arData = $rsData->fetch()) {

        showSuccessMessage('Найден хайлоад-блок ' . $highloadID);

        $Entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arData);
        $arResult = array();
        $arResult['HIGHLOAD']['NAME'] = $arData['NAME'];
        $arResult['HIGHLOAD']['TABLE_NAME'] = $arData['TABLE_NAME'];


        showSuccessMessage('Выборка связанных пользовательских полей');

        $arFields = CUserTypeEntity::GetList(
            array(), array(
                'ENTITY_ID' => 'HLBLOCK_' . $highloadID
            )
        );

        $arNewFields = array();
        $arPropertyFile = false;
        while ($fields = $arFields->Fetch()) {
            unset($fields['ID']);
            unset($fields['ENTITY_ID']);
            $arNewFields[] = $fields;
            //предположим, что в хайлоаде только одна картинка на элемент
            if ($fields['USER_TYPE_ID'] == 'file' && !$arPropertyFile) {
                $arPropertyFile = $fields['FIELD_NAME'];
            }
        }

        $arResult['FIELDS'] = $arNewFields;

        showSuccessMessage('Выгрузка данных');

        // Создадим объект - запрос
        $Query = new \Bitrix\Main\Entity\Query($Entity);
        // Зададим параметры запроса
        $Query->setSelect(Array('*'));
        // Выполним запрос
        $result = $Query->exec();
        // Получаем результат
        $highloadData = array();
        while ($res = $result->fetch()) {
            unset($res['ID']);
            if ($res[$arPropertyFile]) {
                $res['FILE'] = $arPropertyFile;
                $path = \CFile::GetPath($res[$arPropertyFile]);
                $res['URL'] = $_SERVER['SERVER_NAME'] . $path;
            }
            $highloadData[] = $res;
        }

        if($highloadData){
            showSuccessMessage('Количество выгруженных элементов ' . count($highloadData));
            $arResult['DATA'] = $highloadData;
        }

        showSuccessMessage('Сохранение результата в файл');

        $fileName = 'data' . $highloadID . '.json';
        $filePath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $fileName; //абсолютный путь до файла

        file_put_contents($filePath, json_encode($arResult));

        //относительный путь
        $arPath = 'http://' . $_SERVER['SERVER_NAME'] . DIRECTORY_SEPARATOR . $fileName;

        showSuccessMessage('Скопируйте ссылку ' .$arPath);
    }
    else {
        showErrorMessage('Не удалось загрузить хайлоад по id');
    }
    return;
}

if ($_POST['importPath']) {
    \Bitrix\Main\Loader::IncludeModule('highloadblock');

    $data = json_decode(file_get_contents($_POST['importPath']), true);

    if ($data['HIGHLOAD']) {
        showSuccessMessage('Пытаемся создать новый хайлоад');
        $res = \Bitrix\Highloadblock\HighloadBlockTable::add(array(
            'NAME' => $data['HIGHLOAD']['NAME'],
            'TABLE_NAME' => $data['HIGHLOAD']['TABLE_NAME'],
        ))->getId();

        if (!$res) {
            showErrorMessage('Не удалось создать новый хайлоад');
        }

        showSuccessMessage('Создан новый хайлоад ID ' . $res );


        if ($data['FIELDS'] && $res) {
            showSuccessMessage('Создаем пользовательские поля для нового хайлоада');
            foreach ($data['FIELDS'] as $item) {
                $item['ENTITY_ID'] = 'HLBLOCK_' . $res;
                $oUserTypeEntity = new CUserTypeEntity();
                $iUserFieldId = $oUserTypeEntity->Add($item); // int
            }
        }

        if ($data['DATA']) {
            showSuccessMessage('Пытаемся вставить данные');
            $entityData = \Bitrix\Highloadblock\HighloadBlockTable::getById($res);
            if ($arData = $entityData->fetch()) {
                $Entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($arData);
                $DataClass = $Entity->getDataClass();
            }

            if (!$DataClass) {
                showErrorMessage('Ошибка при создании $DataClass для работы с хайлоадом');
            }

            $countAllData = count($data['DATA']);
            $count = 0;

            foreach ($data['DATA'] as $item) {

                if ($item['FILE'] && $item['URL']) {

                    $arFile = CFile::MakeFileArray("http://" . $item['URL']);
                    $fid = CFile::SaveFile($arFile, "highload");

                    $item[$item['FILE']] = $fid;
                    unset($item['FILE']);
                    unset($item['URL']);
                }
//
                $result = $DataClass::add($item);

                if ($result->isSuccess()) {
                    $count++;
                }
            }

            showSuccessMessage('Вставлено: ' . $count . ' из ' . $countAllData);

        }
    }
}


function showSuccessMessage($message){
    echo '<br><p style="color:green; font-weight: bold;">' . $message . '</p>';
}

function showErrorMessage($message){
    echo '<br><p style="color:darkred; font-weight: bold;">' . $message . '</p>';
    die();
}