<?php

test('homepage redirects to projects', function () {
    $this->get('/')->assertRedirect('/projects');
});
