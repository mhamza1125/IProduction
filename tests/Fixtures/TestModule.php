<?php

namespace Tests\Fixtures;

use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\PermissionSet;

class TestModule extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'TestModule';
    }

    public function permissions(): array
    {
        return [new PermissionSet('test_documents', 'Test documents', PermissionSet::DOCUMENT)];
    }

    public function documentTypes(): array
    {
        return [new DocumentType('test_document', 'Test document', 'TD')];
    }
}
