<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $crm = new Bitrix();
    $crm->call('crm.lead.list', ['select' => ['ID'], 'start' => -1], 'crm_leads', 'Проверьте режим CRM с лидами и права вебхука.');
    foreach ([$crm->priorityField => 'enumeration', $crm->uuidField => 'string'] as $field => $type) {
        $result = $crm->call('crm.lead.userfield.list', [
            'filter' => ['FIELD_NAME' => $field],
        ], 'crm_fields', 'Не удалось прочитать пользовательские поля.');
        $item = $result[0] ?? null;
        if (!is_array($item) || ($item['FIELD_NAME'] ?? null) !== $field
            || ($item['USER_TYPE_ID'] ?? null) !== $type || ($item['MULTIPLE'] ?? null) !== 'N') {
            throw new IntegrationFailure('crm_field_type', "Проверьте код, тип и одиночность поля $field.");
        }
        if ($type === 'enumeration') {
            $options = [];
            foreach ($item['LIST'] ?? [] as $option) {
                $options[(string) $option['ID']] = $option['VALUE'];
            }
            $labels = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий'];
            foreach ($crm->priorityValues as $priority => $optionId) {
                if (($options[$optionId] ?? null) !== $labels[$priority]) {
                    throw new IntegrationFailure('crm_priority_config', "Проверьте ID значения «{$labels[$priority]}».");
                }
            }
        }
    }
    echo "Чтение лидов и настройки обоих полей проверены.\n";
} catch (IntegrationFailure $failure) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Проверка не выполнена. Проверьте PHP и локальные настройки.\n");
    exit(1);
}
