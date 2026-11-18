<?php
// GET /api/v1/faktury/dalsie-cislo
Api::json(['cislo_faktury' => nextInvoiceNumber()]);
