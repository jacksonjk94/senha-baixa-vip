<?php

// Hardening para o ambiente serverless da Vercel (dirs em /tmp,
// drivers sem disco persistente, SQLite a partir do snapshot).
// require __DIR__ . '/vercel-bootstrap.php';

// Forward Vercel requests to normal index.php
require __DIR__ . '/../public/index.php';
