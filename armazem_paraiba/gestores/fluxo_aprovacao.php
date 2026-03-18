<?php
// Arquivo: fluxo_aprovacao.php

/**
 * Este arquivo define a HIERARQUIA DE APROVAÇÃO.
 * As strings de 'cargo' e 'setor' devem ser IDÊNTICAS às salvas no banco de dados.
 */

return [
    // REGRA 1: Técnicos da UTI são aprovados por Enfermeiros da UTI.
    // REGRA 1: UTI
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'UTI - Unidade de Tratamento Intensivo'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'UTI - Unidade de Tratamento Intensivo']
    ],

    // REGRA 2: CENTRO CIRURGICO
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'CENTRO CIRURGICO'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'CENTRO CIRURGICO']
    ],

    // REGRA 3
    [
        'solicitante' => ['cargo' => 'Enfermeiro (a)'],
        'aprovador'   => ['cargo' => 'Gerente de Enfermagem']
    ],

    // REGRA 4
    [
        'solicitante' => ['cargo' => 'Gerente de Enfermagem'],
        'aprovador'   => ['cargo' => 'Diretor']
    ],

    // REGRA 5
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'C.C.I.HOSPITALAR'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'C.C.I.HOSPITALAR']
    ],

    // REGRA 6
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Departamento Pessoal - DP'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Departamento Pessoal - DP']
    ],

    // REGRA 7
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Recursos Humanos - RH'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Recursos Humanos - RH']
    ],

    // REGRA 8
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Jurídico'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Jurídico']
    ],

    // REGRA 9
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Transporte'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Transporte']
    ],

    // REGRA 10
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Logística'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Logística']
    ],

    // REGRA 11
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Operações'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Operações']
    ],

    // REGRA 12
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Suporte de Atendimento ao Cliente'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Suporte de Atendimento ao Cliente']
    ],

    // REGRA 13
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Serviços - Suporte Interno'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Serviços - Suporte Interno']
    ],

    // REGRA 14
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Serviços - Gerais'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Serviços - Gerais']
    ],

    // REGRA 15
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Marketing'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Marketing']
    ],

    // REGRA 16
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Vendas'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Vendas']
    ],

    // REGRA 17
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Comercial'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Comercial']
    ],

    // REGRA 18
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Tecnologia da Informação (TI)'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Tecnologia da Informação (TI)']
    ],

    // REGRA 19
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Qualidade'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Qualidade']
    ],

    // REGRA 20
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Pesquisa e Desenvolvimento (PD)'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Pesquisa e Desenvolvimento (PD)']
    ],

    // REGRA 21
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Sustentabilidade/ESG-'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Sustentabilidade/ESG-']
    ],

    // REGRA 22
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'Ouvidoria'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'Ouvidoria']
    ],

    // REGRA 23
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'ENFERMAGEM'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'ENFERMAGEM']
    ],

    // REGRA 24
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'PRONTO SOCORRO'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'PRONTO SOCORRO']
    ], 
    // REGRA 25
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'CENTRAL ESTERELIZAÇÃO - CME'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'CENTRAL ESTERELIZAÇÃO - CME']
    ],
    //regra 26
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'CENTRAL ESTERELIZAÇÃO - CME', 'subsetor' => 'ENDOSCOPIA'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'CENTRAL ESTERELIZAÇÃO - CME', 'subsetor' => 'ENDOSCOPIA']
    ],
    //regra 26
    [
        'solicitante' => ['cargo' => 'Técnico Enfermagem', 'setor' => 'NOSSA SENHORA DE FÁTIMA'],
        'aprovador'   => ['cargo' => 'Enfermeiro (a)', 'setor' => 'NOSSA SENHORA DE FÁTIMA']
    ],
];
?>