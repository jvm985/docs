<?php

namespace App\Services\Compilers;

interface CompilerInterface
{
    /**
     * Compile a file within a workspace.
     * 
     * @param string $mainFilePath De volledige wegligging van de hoofdbestand op schijf.
     * @param string $workspaceDir De root van de user-workspace voor includes.
     * @return array [type => 'pdf|text', url => '...', output => '...', result => bool]
     */
    public function compile(string $mainFilePath, string $workspaceDir): array;
}
