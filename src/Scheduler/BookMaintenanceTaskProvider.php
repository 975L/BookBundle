<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\BookBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

// The commands this bundle needs run on a cadence: a site installing the catalog gets them scheduled, one removing it stops running them, and neither has anything to edit in its own MaintenanceSchedule
class BookMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Release alerts, nightly: a book is dated to the day, so it comes out at 00:00 and the first run of the night tells everyone waiting
            new MaintenanceTask('# #(1-3) * * *', 'c975l:book:release-alerts:send'),
        ];
    }
}
