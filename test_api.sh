#!/bin/bash
curl -s -X POST http://127.0.0.1:8888/index.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_games","filters":{}}' \
  -b cookies.txt \
  | jq '.data[0] | {id, title, date_started, dateStarted, date_finished, dateFinished, user_rating, hours_played}' 2>/dev/null || echo "No se pudo parsear JSON"
