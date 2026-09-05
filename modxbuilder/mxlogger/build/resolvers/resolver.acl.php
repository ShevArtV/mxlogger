<?php
/**
 * PHP-резолвер mxLogger: права пакета, политика доступа и её назначение.
 *
 * Создаёт (идемпотентно, на install и upgrade):
 *   - шаблон политики mxloggerTemplate;
 *   - права load / mxlogger_view / mxlogger_manage в этом шаблоне;
 *   - политику mxloggerDefault со всеми правами пакета;
 *   - ACL-запись modAccessContext: Administrator → mgr → mxloggerDefault (authority 9999).
 *
 * ⚠️ ИНВАРИАНТ, не ломать: шаблон, права и политика создаются БЕЗУСЛОВНО и до блока
 * назначения групп. Наличие/отсутствие какой-либо группы на выдачу политики не влияет —
 * политика обязана появиться в БД на любом сайте, даже если назначать её оказалось некому.
 *
 * ⚠️ Пакет НЕ знает имён рабочих групп конкретного сайта. Единственная группа, к которой
 * он привязывается, — системная Administrator (id 1): она есть в любой установке MODX.
 * Раньше здесь была ещё и Developers; на sleepandglow её переименовали в Dev, назначение
 * молча пропускалось, и права пакета не доставались никому. Добавлять сюда имена групп
 * сайта нельзя — остальным группам политика выдаётся вручную через админку
 * (Пользователи → Права доступа → группа → Контексты → mgr → mxloggerDefault).
 *
 * Почему отдельной ACL-записью, а не дописыванием прав в политику Administrator:
 * права mxlogger_* объявлены в собственном шаблоне пакета, а политика Administrator
 * живёт на AdministratorTemplate — чужих прав она не примет. Несколько политик на
 * один контекст складываются: modAccessibleObject::checkPolicy() перебирает все
 * ACL-записи пользователя на цель и возвращает true на первом совпадении.
 * authority 9999 берётся с запасом: modAccessContext::loadAttributes() отбирает
 * записи условием mr.authority <= acl.authority, то есть покрывает любые роли.
 *
 * ⚠️ Поле data политики читается ТОЛЬКО через get('data'): xPDO отдаёт его уже
 * массивом (phptype=json). json_decode() поверх массива вернул бы null и обнулил
 * набор прав — так в mpc была почти стёрта политика Administrator.
 *
 * @var xPDOTransport $transport
 * @var array $options
 * @var modX $modx
 */
if (!$transport->xpdo) {
    return true;
}

/** @var modX $modx */
$modx =& $transport->xpdo;

switch ($options[xPDOTransport::PACKAGE_ACTION]) {
    case xPDOTransport::ACTION_INSTALL:
    case xPDOTransport::ACTION_UPGRADE:
        $permissions = array(
            'load'            => 'mxlogger_perm_load',
            'mxlogger_view'   => 'mxlogger_perm_view',
            'mxlogger_manage' => 'mxlogger_perm_manage',
        );

        /* --- Шаблон политики --------------------------------------------- */
        $template = $modx->getObject('modAccessPolicyTemplate', array('name' => 'mxloggerTemplate'));
        if (!$template) {
            $template = $modx->newObject('modAccessPolicyTemplate');
            $template->fromArray(array(
                'template_group' => 1,
                'name'           => 'mxloggerTemplate',
                'description'    => 'Права mxLogger',
                'lexicon'        => 'mxlogger:permissions',
            ), '', true, true);
            if (!$template->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Не удалось создать шаблон политики mxloggerTemplate');
                return true;
            }
        }

        /* --- Права шаблона ------------------------------------------------ */
        foreach ($permissions as $name => $description) {
            if ($modx->getCount('modAccessPermission', array('template' => $template->get('id'), 'name' => $name))) {
                continue;
            }
            $permission = $modx->newObject('modAccessPermission');
            $permission->fromArray(array(
                'template'    => $template->get('id'),
                'name'        => $name,
                'description' => $description,
                'value'       => 1,
            ), '', true, true);
            $permission->save();
        }

        /* --- Политика ----------------------------------------------------- */
        $policy = $modx->getObject('modAccessPolicy', array('name' => 'mxloggerDefault'));
        if (!$policy) {
            $policy = $modx->newObject('modAccessPolicy');
            $policy->fromArray(array(
                'name'        => 'mxloggerDefault',
                'description' => 'Полный доступ к mxLogger',
                'parent'      => 0,
                'template'    => $template->get('id'),
                'class'       => '',
                'lexicon'     => 'mxlogger:permissions',
            ), '', true, true);
        }
        // Только get(), без json_decode — см. шапку файла.
        $data = $policy->get('data');
        if (!is_array($data)) {
            $data = array();
        }
        foreach (array_keys($permissions) as $name) {
            $data[$name] = true;
        }
        $policy->set('data', $data);
        if (!$policy->save()) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Не удалось сохранить политику mxloggerDefault');
            return true;
        }

        /* --- Назначение политики группам ---------------------------------- */
        // Политика выше уже создана и сохранена — этот блок только раздаёт её.
        // Только системная Administrator (id 1); имён рабочих групп сайта здесь быть
        // не должно, см. шапку файла.
        foreach (array('Administrator') as $groupName) {
            /** @var modUserGroup $group */
            $group = $modx->getObject('modUserGroup', array('name' => $groupName));
            if (!$group) {
                // Administrator — системная группа MODX, её отсутствие означает
                // сломанную установку. Ставить не мешаем, но след в лог оставляем.
                $modx->log(modX::LOG_LEVEL_WARN, '[mxLogger] Группа ' . $groupName . ' не найдена — политика mxloggerDefault создана, но не назначена');
                continue;
            }
            $criteria = array(
                'target'          => 'mgr',
                'principal_class' => 'modUserGroup',
                'principal'       => $group->get('id'),
                'policy'          => $policy->get('id'),
            );
            if ($modx->getCount('modAccessContext', $criteria)) {
                continue;
            }
            $acl = $modx->newObject('modAccessContext');
            $acl->fromArray(array_merge($criteria, array('authority' => 9999)), '', true, true);
            if ($acl->save()) {
                $modx->log(modX::LOG_LEVEL_INFO, '[mxLogger] Политика mxloggerDefault назначена группе ' . $groupName . ' на контекст mgr');
            } else {
                $modx->log(modX::LOG_LEVEL_ERROR, '[mxLogger] Не удалось назначить политику группе ' . $groupName);
            }
        }

        /* --- Подстраховка для уже установленных сайтов -------------------- */
        // Vehicle меню на апгрейде существующую запись modMenu не перезаписывает
        // (update_object при существующем объекте отключён), поэтому пустое поле
        // permissions у пункта меню надо проставить руками — иначе пункт останется
        // видимым всем менеджерам.
        $menu = $modx->getObject('modMenu', array('action' => 'index', 'namespace' => 'mxlogger'));
        if ($menu && trim((string) $menu->get('permissions')) === '') {
            $menu->set('permissions', 'mxlogger_view');
            $menu->save();
            $modx->log(modX::LOG_LEVEL_INFO, '[mxLogger] Пункту меню mxlogger проставлено permissions=mxlogger_view');
        }

        // Права меняют состав атрибутов доступа в сессиях — сбрасываем кэш ACL.
        if ($cacheManager = $modx->getCacheManager()) {
            $cacheManager->refresh(array('permissions' => array()));
        }
        break;
}

return true;
