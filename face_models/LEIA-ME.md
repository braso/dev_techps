# Modelos de IA — face-api.js

Esta pasta deve conter os arquivos de modelo do face-api.js para o reconhecimento facial funcionar.

## Como baixar

Execute o script `baixar_modelos.sh` (Linux/Mac) ou acesse os links abaixo e salve os arquivos nesta pasta:

### Arquivos necessários (3 modelos, ~6MB total):

**tiny_face_detector** (detecção rápida de rostos):
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/tiny_face_detector_model-weights_manifest.json
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/tiny_face_detector_model-shard1

**face_landmark_68** (pontos do rosto):
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_landmark_68_model-weights_manifest.json
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_landmark_68_model-shard1

**face_recognition** (geração do descritor 128D):
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_recognition_model-weights_manifest.json
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_recognition_model-shard1
- https://github.com/justadudewhohacks/face-api.js/raw/master/weights/face_recognition_model-shard2

## Script rápido (Linux/Mac/Git Bash)

```bash
cd face_models
BASE="https://github.com/justadudewhohacks/face-api.js/raw/master/weights"
for f in \
  tiny_face_detector_model-weights_manifest.json \
  tiny_face_detector_model-shard1 \
  face_landmark_68_model-weights_manifest.json \
  face_landmark_68_model-shard1 \
  face_recognition_model-weights_manifest.json \
  face_recognition_model-shard1 \
  face_recognition_model-shard2; do
  curl -L "$BASE/$f" -o "$f"
done
```

## Estrutura esperada

```
face_models/
  tiny_face_detector_model-weights_manifest.json
  tiny_face_detector_model-shard1
  face_landmark_68_model-weights_manifest.json
  face_landmark_68_model-shard1
  face_recognition_model-weights_manifest.json
  face_recognition_model-shard1
  face_recognition_model-shard2
```
