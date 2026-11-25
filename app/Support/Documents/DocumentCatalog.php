<?php

namespace App\Support\Documents;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Finds every document model (classes in app/Modules/{Name}/Models that
 * implement Document) and the screen that shows one, so cross-module lists
 * such as "Awaiting your approval" need no registration.
 */
class DocumentCatalog
{
    /** @var list<class-string<Model&Document>>|null */
    private ?array $classes = null;

    /** @var array<class-string, array{0: string, 1: string}|false> */
    private array $routes = [];

    /** @return list<class-string<Model&Document>> */
    public function classes(): array
    {
        if ($this->classes !== null) {
            return $this->classes;
        }

        $found = [];
        foreach (File::glob(app_path('Modules/*/Models/*.php')) as $file) {
            $module = basename(dirname($file, 2));
            $class = 'App\\Modules\\'.$module.'\\Models\\'.basename($file, '.php');
            if (class_exists($class) && is_subclass_of($class, Document::class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $found[] = $class;
            }
        }
        sort($found);

        return $this->classes = $found;
    }

    /** URL of the screen that shows $document, or null if none is found. */
    public function url(Model&Document $document): ?string
    {
        if (method_exists($document, 'showUrl')) {
            return $document->showUrl();
        }
        $route = $this->routes[$document::class] ??= $this->findRoute($document::class);

        return $route === false ? null : route($route[0], [$route[1] => $document->getKey()]);
    }

    /**
     * A named GET route ending in ".show" whose Livewire component takes this model.
     *
     * @param  class-string  $class
     * @return array{0: string, 1: string}|false [route name, parameter name]
     */
    private function findRoute(string $class): array|false
    {
        foreach (Router::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            $component = $route->getAction('controller');
            if ($name === null || ! str_ends_with($name, '.show') || ! is_string($component) || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $component = explode('@', $component)[0];
            if (! class_exists($component) || ! method_exists($component, 'mount')) {
                continue;
            }
            foreach ((new \ReflectionMethod($component, 'mount'))->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && $type->getName() === $class && in_array($parameter->getName(), $route->parameterNames(), true)) {
                    return [$name, $parameter->getName()];
                }
            }
        }

        return false;
    }
}
