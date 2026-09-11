<?php

namespace App\Services\Publishing;

// ошибка публикации в площадку с понятным человеку текстом — попадает в карточку публикации
class PublishException extends \RuntimeException
{
}
