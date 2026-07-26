<?php
/**
 * config.php - Configurações globais da aplicação
 * NUNCA expões este ficheiro publicamente.
 * Já está bloqueado no .htaccess.
 */

// Groq API
// a key vem do .htaccess (SetEnv), nao fica no codigo
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');

//  - 'llama-3.1-8b-instant'                           → o mais rápido, menos capaz
define('GROQ_MODEL',   'meta-llama/llama-4-scout-17b-16e-instruct');
define('GROQ_URL',     'https://api.groq.com/openai/v1/chat/completions');
