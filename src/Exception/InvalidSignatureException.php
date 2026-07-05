<?php

namespace App\Exception;

/**
 * Se lanza cuando la firma de origen de un evento no puede validarse contra
 * la clave pública registrada de la organización. El evento NO se persiste.
 */
class InvalidSignatureException extends \RuntimeException
{
    public function __construct(string $message = 'La firma de origen no es válida para la clave pública registrada.')
    {
        parent::__construct($message);
    }
}
