<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$template = App\Models\PrintTemplate::find(1);

if (! $template) {
    throw new RuntimeException('Template 1 not found');
}

$body = (string) $template->template_body;

if (str_contains($body, 'Адреса:')) {
    fwrite(STDOUT, "ALREADY_PRESENT\n");
    return;
}

$phoneBlock = <<<'TWIG'
<div>
<strong>Телефон:</strong>
{{ order.phone|default(phone|default("-")) }}
</div>
TWIG;

$addressBlock = <<<'TWIG'
<div>
<strong>Адреса:</strong>
{{ order.address_line|default(address|default("-")) }}
</div>
TWIG;

if (! str_contains($body, $phoneBlock)) {
    throw new RuntimeException('Phone block not found in template 1');
}

$template->template_body = str_replace($phoneBlock, $phoneBlock . PHP_EOL . $addressBlock, $body, $count);

if ($count < 1) {
    throw new RuntimeException('Template 1 was not updated');
}

$template->save();

fwrite(STDOUT, "UPDATED\n");
