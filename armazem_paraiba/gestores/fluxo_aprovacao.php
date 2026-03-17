<?php
// Arquivo: fluxo_aprovacao.php

/**
 * Este arquivo define a HIERARQUIA DE APROVAÇÃO.
 * As strings de 'cargo' e 'setor' devem ser IDÊNTICAS às salvas no banco de dados.
 */

return [
    // REGRA 1: Técnicos da UTI são aprovados por Enfermeiros da UTI.
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'UTI - Unidade de Tratamento Intensivo'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)',     'setor' => 'UTI - Unidade de Tratamento Intensivo']
    ],

    // REGRA 2: Para o CENTRO CIRÚRGICO
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'CENTRO CIRURGICO'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)',     'setor' => 'CENTRO CIRURGICO']
    ],

    // REGRA 3: Enfermeiros(as) de QUALQUER setor são aprovados pelo Gerente de Enfermagem.
    [
        'solicitante' => ['cargo' => 'Enfermeiro (a)'],
        'aprovador'   => ['cargo' => 'Gerente de Enfermagem']
    ],

    // REGRA 4: O Gerente de Enfermagem é aprovado pelo Diretor. (VÍRGULA ADICIONADA)
    [
        'solicitante' => ['cargo' => 'Gerente de Enfermagem'],
        'aprovador'   => ['cargo' => 'Diretor']
    ],
    
    // REGRA 5: Setor C.C.I.HOSPITALAR.
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'C.C.I.HOSPITALAR'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)',     'setor' => 'C.C.I.HOSPITALAR']
    ], // <-- A vírgula aqui não é estritamente necessária, mas adicioná-la em todas as regras, exceto a última, é uma boa prática para evitar erros como o que aconteceu.

];
?>