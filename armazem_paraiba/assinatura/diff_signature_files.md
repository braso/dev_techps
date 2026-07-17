# Alterações nos Arquivos do Módulo de Assinatura

Este documento contém o diff detalhado das modificações realizadas nos arquivos do diretório `armazem_paraiba/assinatura/` para implementar o bypass de responsáveis (onde a assinatura de um gestor dispensa os outros gestores pendentes).

---

## 1. [assinar.php](file:///C:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/assinatura/assinar.php)

```diff
@@ -651,6 +651,13 @@
         mysqli_stmt_close($stmtUpd);
     }
 
+    // Se o assinante que acabou de assinar tem funcao 'Responsável', vamos marcar todos os outros assinantes com funcao 'Responsável' desta mesma solicitação como 'dispensado'.
+    // Isso garante que apenas um responsável precise assinar.
+    $funcaoAssinante = strtolower(trim(strval($row["funcao"] ?? "")));
+    if ($funcaoAssinante === 'responsável') {
+        mysqli_query($conn, "UPDATE assinantes SET status = 'dispensado' WHERE id_solicitacao = {$idSolicitacao} AND LOWER(funcao) = 'responsável' AND status = 'pendente'");
+    }
+
     $stmtUpSol = mysqli_prepare($conn, "UPDATE solicitacoes_assinatura SET caminho_arquivo = ?, status = 'em_progresso' WHERE id = ? LIMIT 1");
     if ($stmtUpSol) {
         mysqli_stmt_bind_param($stmtUpSol, "si", $caminhoRel, $idSolicitacao);
@@ -694,7 +701,7 @@
 }
 $total = 0;
 $assinados = 0;
-$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) = 'assinado' THEN 1 ELSE 0 END) as assinados FROM assinantes WHERE id_solicitacao = ?");
+$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) IN ('assinado', 'dispensado') THEN 1 ELSE 0 END) as assinados FROM assinantes WHERE id_solicitacao = ?");
 if ($stmtCount) {
     mysqli_stmt_bind_param($stmtCount, "i", $idSolicitacao);
     mysqli_stmt_execute($stmtCount);
@@ -707,7 +714,7 @@
 $ultimo = ($total > 0 && $assinados >= $total);
 $pendentes = [];
 if (!$ultimo) {
-    $stmtPend = mysqli_prepare($conn, "SELECT ordem, nome, funcao FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) <> 'assinado' ORDER BY ordem ASC, id ASC");
+    $stmtPend = mysqli_prepare($conn, "SELECT ordem, nome, funcao FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) NOT IN ('assinado', 'dispensado') ORDER BY ordem ASC, id ASC");
     if ($stmtPend) {
         mysqli_stmt_bind_param($stmtPend, "i", $idSolicitacao);
         mysqli_stmt_execute($stmtPend);
@@ -726,7 +733,7 @@
 }
 
 if (!$ultimo) {
-    $stmtNext = mysqli_prepare($conn, "SELECT nome, email, token, funcao, enti_nb_id FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) <> 'assinado' ORDER BY ordem ASC, id ASC LIMIT 1");
+    $stmtNext = mysqli_prepare($conn, "SELECT nome, email, token, funcao, enti_nb_id FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) NOT IN ('assinado', 'dispensado') ORDER BY ordem ASC, id ASC LIMIT 1");
     if ($stmtNext) {
         mysqli_stmt_bind_param($stmtNext, "i", $idSolicitacao);
         mysqli_stmt_execute($stmtNext);
```

---

## 2. [assinar_via_link.php](file:///C:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/assinatura/assinar_via_link.php)

```diff
@@ -339,7 +339,7 @@
     $idSolicitacao = intval($assinante["id_solicitacao"]);
     $total = 0;
     $assinados = 0;
-    $stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) = 'assinado' THEN 1 ELSE 0 END) as assinados FROM assinantes WHERE id_solicitacao = ?");
+    $stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN LOWER(status) IN ('assinado', 'dispensado') THEN 1 ELSE 0 END) as assinados FROM assinantes WHERE id_solicitacao = ?");
     if ($stmtCount) {
         mysqli_stmt_bind_param($stmtCount, "i", $idSolicitacao);
         mysqli_stmt_execute($stmtCount);
@@ -351,7 +351,7 @@
     $assinatura_is_final = ($total > 0 && $assinados >= $total);
 
     if (!$assinatura_is_final) {
-        $stmtPend = mysqli_prepare($conn, "SELECT ordem, nome, funcao FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) <> 'assinado' ORDER BY ordem ASC, id ASC");
+        $stmtPend = mysqli_prepare($conn, "SELECT ordem, nome, funcao FROM assinantes WHERE id_solicitacao = ? AND LOWER(status) NOT IN ('assinado', 'dispensado') ORDER BY ordem ASC, id ASC");
         if ($stmtPend) {
             mysqli_stmt_bind_param($stmtPend, "i", $idSolicitacao);
             mysqli_stmt_execute($stmtPend);
```

---

## 3. [pendentes.php](file:///C:/Users/Ornilio%20Neto/Documents/Techps/dev_techps/armazem_paraiba/assinatura/pendentes.php)

```diff
@@ -42,12 +42,12 @@
 		JOIN solicitacoes_assinatura s ON s.id = a.id_solicitacao
 		LEFT JOIN tipos_documentos t ON t.tipo_nb_id = s.tipo_documento_id
 		WHERE a.enti_nb_id = ?
-			AND LOWER(TRIM(a.status)) <> 'assinado'
+			AND LOWER(TRIM(a.status)) = 'pendente'
 			AND a.ordem = (
 				SELECT MIN(a2.ordem)
 				FROM assinantes a2
 				WHERE a2.id_solicitacao = a.id_solicitacao
-					AND LOWER(TRIM(a2.status)) <> 'assinado'
+					AND LOWER(TRIM(a2.status)) = 'pendente'
 			)
 			AND (s.status = 'pendente' OR s.status = 'em_progresso')
 		ORDER BY s.data_solicitacao DESC, s.id DESC
```
