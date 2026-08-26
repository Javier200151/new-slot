<?php

namespace Tests\Feature;

use App\Policies\CrudPolicy;
use App\Support\PermissionCatalog;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Gate;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RegexIterator;
use Tests\TestCase;

class FilamentResourcePolicyCoverageTest extends TestCase
{
    public function test_every_filament_resource_has_an_effective_policy(): void
    {
        $resourcesWithoutPolicy = [];

        foreach ($this->filamentResourceClasses() as $resourceClass) {
            $model = $resourceClass::getModel();

            if (Gate::getPolicyFor($model) === null) {
                $resourcesWithoutPolicy[] =
                    "{$resourceClass} -> {$model}";
            }
        }

        $this->assertSame(
            [],
            $resourcesWithoutPolicy,
            "Resources de Filament sin policy efectiva:\n"
                . implode("\n", $resourcesWithoutPolicy)
        );
    }

    public function test_crud_policies_reference_a_known_permission_resource(): void
    {
        $knownResources = PermissionCatalog::resources();
        $unknownPermissionResources = [];

        foreach ($this->filamentResourceClasses() as $resourceClass) {
            $policy = Gate::getPolicyFor(
                $resourceClass::getModel()
            );

            if (! $policy instanceof CrudPolicy) {
                continue;
            }

            $reflection = new ReflectionClass($policy);
            $property = $reflection->getProperty('resource');
            $resource = $property->getValue($policy);

            if (! array_key_exists($resource, $knownResources)) {
                $unknownPermissionResources[] =
                    "{$resourceClass} -> {$resource}";
            }
        }

        $this->assertSame(
            [],
            $unknownPermissionResources,
            "Policies CRUD con un recurso que no existe en PermissionCatalog:\n"
                . implode("\n", $unknownPermissionResources)
        );
    }

    /**
     * @return array<class-string<Resource>>
     */
    private function filamentResourceClasses(): array
    {
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    app_path('Filament/Resources')
                )
            ),
            '/Resource\\.php$/i'
        );

        $resources = [];

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = str_replace(
                [app_path() . DIRECTORY_SEPARATOR, '.php'],
                ['', ''],
                $path
            );

            $class = 'App\\' . str_replace(
                DIRECTORY_SEPARATOR,
                '\\',
                $relative
            );

            if (
                class_exists($class)
                && is_subclass_of($class, Resource::class)
            ) {
                $resources[] = $class;
            }
        }

        sort($resources);

        return $resources;
    }
}
