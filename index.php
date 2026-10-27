<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Send visitors to the dashboard (it sends them to login if needed)
redirect('dashboard.php');
