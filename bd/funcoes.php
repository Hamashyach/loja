<?php

/**
 * Gera um slug (URL amigável) a partir de uma string.
 * Ex: "Tênis da Hora" -> "tenis-da-hora"
 */
function gerar_slug($texto) {
    // 1. Converte para minúsculas
    $texto = strtolower($texto);
    $texto = preg_replace('/[áàãâä]/u', 'a', $texto);
    $texto = preg_replace('/[éèêë]/u', 'e', $texto);
    $texto = preg_replace('/[íìîï]/u', 'i', $texto);
    $texto = preg_replace('/[óòõôö]/u', 'o', $texto);
    $texto = preg_replace('/[úùûü]/u', 'u', $texto);
    $texto = preg_replace('/[ç]/u', 'c', $texto);
    // 3. Remove caracteres que não são letras, números ou espaços/hífens
    $texto = preg_replace('/[^a-z0-9\s-]/', '', $texto);
    // 4. Substitui espaços por hífens
    $texto = preg_replace('/[\s-]+/', '-', $texto);
    // 5. Remove hífens extras do início ou fim
    $texto = trim($texto, '-');
    
    return $texto;
}
?>