<?php

    header("Expires: 01 Jan 2001 00:00:00 GMT");
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header('Cache-Control: post-check=0, pre-check=0', FALSE);
    header('Pragma: no-cache');

    require "../funcoes_ponto.php";

    header("Content-Type: application/json; charset=utf-8");

    $empresa = isset($_GET["empresa"]) ? (int)$_GET["empresa"] : 0;
    $matricula = isset($_GET["matricula"]) ? trim($_GET["matricula"]) : "";
    $dataRef = isset($_GET["data"]) ? trim($_GET["data"]) : "";

    if (!$empresa || $matricula === "" || $dataRef === "") {
        echo json_encode([]);
        exit;
    }

    $dataObj = DateTime::createFromFormat("d/m/Y", $dataRef);
    if (!$dataObj) {
        echo json_encode([]);
        exit;
    }

    $periodoInicio = new DateTime($dataObj->format("Y-m-01"));
    $periodoFim = new DateTime($dataObj->format("Y-m-t"));

    $motoristas = mysqli_fetch_all(query(
        "SELECT entidade.*, 
                empresa.empr_nb_parametro,
                operacao.oper_tx_nome,
                grupos_documentos.grup_tx_nome,
                sbgrupos_documentos.sbgr_tx_nome
         FROM entidade
         LEFT JOIN empresa ON entidade.enti_nb_empresa = empresa.empr_nb_id
         LEFT JOIN operacao ON oper_nb_id = entidade.enti_tx_tipoOperacao
         LEFT JOIN grupos_documentos ON grup_nb_id = entidade.enti_setor_id
         LEFT JOIN sbgrupos_documentos ON sbgr_nb_id = entidade.enti_subSetor_id
         WHERE entidade.enti_tx_status = 'ativo'
           AND entidade.enti_nb_empresa = ".$empresa."
           AND entidade.enti_tx_matricula = '".$matricula."'
         LIMIT 1;"
    ), MYSQLI_ASSOC);

    if (empty($motoristas)) {
        echo json_encode([]);
        exit;
    }

    $motorista = $motoristas[0];
    $diasSemana = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SAB"];
    $resultado = [];

    for ($data = clone $periodoInicio; $data <= $periodoFim; $data->modify("+1 day")) {
        $dataStr = $data->format("Y-m-d");
        $diaPonto = diaDetalhePonto($motorista, $dataStr);
        $inicioEscala = $diaPonto["inicioEscala"] ?? "";
        $fimEscala = $diaPonto["fimEscala"] ?? "";
        $inicio = (!empty($inicioEscala) && $inicioEscala !== "00:00" && $inicioEscala !== "00:00:00") ? substr($inicioEscala, 0, 5) : "--:--";
        $fim = (!empty($fimEscala) && $fimEscala !== "00:00" && $fimEscala !== "00:00:00") ? substr($fimEscala, 0, 5) : "--:--";
        $valor = ($inicio === "--:--" && $fim === "--:--") ? "----" : $inicio." - ".$fim;

        $resultado[] = [
            "data" => $data->format("d/m/Y"),
            "diaSemana" => $diasSemana[(int)$data->format("w")],
            "escala" => $valor
        ];
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;

