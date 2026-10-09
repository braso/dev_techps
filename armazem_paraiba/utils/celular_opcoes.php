<?php
// Opções do cadastro de celulares/ativos, compartilhadas entre o cadastro e o
// módulo de termos para que alterações reflitam em ambos.

if(!function_exists("opcoesTipoCelular")){
    function opcoesTipoCelular(){
        return [
            "Celular" => "Celular",
            "Tablet"  => "Tablet",
            "Rádio"   => "Rádio",
            "Outro"   => "Outro",
        ];
    }
}

if(!function_exists("opcoesAcessorios")){
    function opcoesAcessorios(){
        return [
            "carregador"       => "Carregador",
            "cabo_usb"         => "Cabo USB",
            "capa"             => "Capa",
            "pelicula"         => "Película",
            "suporte_veicular" => "Suporte veicular",
            "outros"           => "Outros",
        ];
    }
}

if(!function_exists("opcoesAplicativos")){
    function opcoesAplicativos(){
        return [
            "tp_map"               => "TP MAP",
            "whatsapp_corporativo" => "WhatsApp corporativo",
            "outros_aplicativos"   => "Outros",
        ];
    }
}

if(!function_exists("opcoesEstadoConservacao")){
    function opcoesEstadoConservacao(){
        return [
            "Novo"               => "Novo",
            "Usado – bom estado" => "Usado – bom estado",
        ];
    }
}
