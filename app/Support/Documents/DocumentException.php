<?php

namespace App\Support\Documents;

use RuntimeException;

/**
 * A business-rule failure the user should see, e.g. "This document is already posted"
 * or "Not enough stock". Livewire components show its message as an error toast.
 */
class DocumentException extends RuntimeException {}
