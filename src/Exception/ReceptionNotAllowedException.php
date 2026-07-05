<?php

namespace App\Exception;

/**
 * Se lanza cuando se intenta confirmar la recepción (firma de destino) de un
 * evento que no lo admite: porque no es un OUT_DISPATCH, porque ya está
 * consolidado, o porque no está EN_TRANSITO. El evento no se modifica.
 */
class ReceptionNotAllowedException extends \RuntimeException
{
}
