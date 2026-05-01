<?php

namespace App\Services;

use App\Models\Project;

class LinkRegistry
{
    public function file(Project $project): string
    {
        $dir = storage_path('app/private/projects/'.$project->id);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.'/links.json';
    }

    /** @return array<string,array{source_project_id:int,source_path:string,copied_at:int}> */
    public function all(Project $project): array
    {
        $f = $this->file($project);
        if (! is_file($f)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($f), true);

        return is_array($data) ? $data : [];
    }

    public function get(Project $project, string $path): ?array
    {
        $links = $this->all($project);

        return $links[$path] ?? null;
    }

    public function isLinked(Project $project, string $path): bool
    {
        return $this->get($project, $path) !== null;
    }

    public function set(Project $project, string $path, int $sourceProjectId, string $sourcePath): void
    {
        $links = $this->all($project);
        $links[$path] = [
            'source_project_id' => $sourceProjectId,
            'source_path' => $sourcePath,
            'copied_at' => time(),
        ];
        $this->persist($project, $links);
    }

    public function remove(Project $project, string $path): void
    {
        $links = $this->all($project);
        if (! isset($links[$path])) {
            return;
        }
        unset($links[$path]);
        $this->persist($project, $links);
    }

    public function removePrefix(Project $project, string $prefix): void
    {
        $links = $this->all($project);
        $changed = false;
        $prefix = rtrim($prefix, '/');
        foreach (array_keys($links) as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix.'/')) {
                unset($links[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $this->persist($project, $links);
        }
    }

    public function rename(Project $project, string $oldPath, string $newPath): void
    {
        $links = $this->all($project);
        $changed = false;
        $oldPrefix = rtrim($oldPath, '/');
        foreach (array_keys($links) as $key) {
            if ($key === $oldPrefix) {
                $links[$newPath] = $links[$key];
                unset($links[$key]);
                $changed = true;
            } elseif (str_starts_with($key, $oldPrefix.'/')) {
                $suffix = substr($key, strlen($oldPrefix) + 1);
                $links[$newPath.'/'.$suffix] = $links[$key];
                unset($links[$key]);
                $changed = true;
            }
        }
        if ($changed) {
            $this->persist($project, $links);
        }
    }

    private function persist(Project $project, array $links): void
    {
        ksort($links);
        @file_put_contents(
            $this->file($project),
            json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
