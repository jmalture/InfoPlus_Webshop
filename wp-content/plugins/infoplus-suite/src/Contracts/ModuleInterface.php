<?php
namespace Infoplus\Suite\Contracts;

interface ModuleInterface {
    public function slug(): string;
    public function label(): string;
    public function register(): void; 
}
