<?php

test('homepage redirects to admin', function () {
    $this->get('/')->assertRedirect('/admin');
});
