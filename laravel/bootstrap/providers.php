<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Modules\OAuthLogin\Providers\OAuthLoginServiceProvider::class,
    Modules\MailClient\Providers\MailClientServiceProvider::class,
    Modules\Workflow\Providers\WorkflowServiceProvider::class,
    Modules\Knowledge\Providers\KnowledgeServiceProvider::class,
    Modules\AIReply\Providers\AIReplyServiceProvider::class,
];
