<?php
namespace Modules\HostDashboardSelector;
use Zabbix\Core\CModule;
use APP;
use CMenuItem;
class Module extends CModule 
{
    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Monitoring'))
                ->getSubmenu()
                    ->insertAfter(_('Discovery'),((new CMenuItem(_('Host Dashboard Selector')))
                        ->setAction('host.dashboard.selector'))
                    );
    }
}
