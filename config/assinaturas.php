<?php

/**
 * Retorna o nome da coluna de chave primária da tabela assinaturas
 * (padrão: id_assinatura).
 */
function pkAssinatura($conn)
{
    static $pk = null;

    if ($pk !== null) {
        return $pk;
    }

    $pk = 'id_assinatura';

    $res = @$conn->query("SHOW KEYS FROM assinaturas WHERE Key_name = 'PRIMARY'");

    if ($res && ($row = $res->fetch_assoc())) {
        $pk = $row['Column_name'];
    }

    return $pk;
}

