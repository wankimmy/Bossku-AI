<?php

namespace App\Services\Project;

/** Raised when a surgical edit's oldString matches more than one span. */
class EditAmbiguousException extends \RuntimeException {}
