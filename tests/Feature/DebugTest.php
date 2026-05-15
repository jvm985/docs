<?php

test('debug test class hierarchy', function () {
    $class = new \ReflectionClass($this);
    $traits = class_uses_recursive(static::class);
    file_put_contents('/tmp/test-debug.log', "TEST CLASS: ".$class->getName()."\n", FILE_APPEND);
    $parent = $class->getParentClass();
    while ($parent) {
        file_put_contents('/tmp/test-debug.log', "  -> ".$parent->getName()."\n", FILE_APPEND);
        $parent = $parent->getParentClass();
    }
    file_put_contents('/tmp/test-debug.log', "TRAITS: ".implode(', ', array_keys($traits))."\n", FILE_APPEND);
});
