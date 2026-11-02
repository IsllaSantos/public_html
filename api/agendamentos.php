<?php
header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode(['sucesso' => false, 'erro' => 'Endpoint descontinuado. Utilize secretaria_agenda_slots.php ou secretaria_agendar_consulta.php.']);
