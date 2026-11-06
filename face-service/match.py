"""Compare a kiosk photo with a patron's Koha photo and print one line of JSON.

Usage (from anywhere):
  python match.py KOHA_PHOTO KIOSK_PHOTO [--threshold 0.363]

Output, one JSON line on stdout:
  {"status": "matched", "score": 0.595, "faces": 1}
status is one of: matched, mismatch, no_face (no face in the kiosk photo),
reference_unusable (no face in the Koha photo), error (exit code 1).
"""
import argparse
import json
import os
import sys
from pathlib import Path

os.environ.setdefault("OPENCV_LOG_LEVEL", "ERROR")

import cv2  # noqa: E402
import numpy as np  # noqa: E402

HERE = Path(__file__).resolve().parent
DETECTOR_MODEL = HERE / "models" / "face_detection_yunet_2023mar.onnx"
RECOGNIZER_MODEL = HERE / "models" / "face_recognition_sface_2021dec.onnx"

DEFAULT_THRESHOLD = 0.363
DETECT_THRESHOLD = 0.9


def fail(message):
    print(json.dumps({"status": "error", "message": message}))
    sys.exit(1)


def load_models():
    for model in (DETECTOR_MODEL, RECOGNIZER_MODEL):
        if not model.is_file():
            fail(f"Model file missing: {model}")
    detector = cv2.FaceDetectorYN.create(str(DETECTOR_MODEL), "", (320, 320), DETECT_THRESHOLD, 0.3, 5000)
    recognizer = cv2.FaceRecognizerSF.create(str(RECOGNIZER_MODEL), "")
    return detector, recognizer


def prepare(img):
    """Bring the longest side into the 320 to 640 pixel range."""
    h, w = img.shape[:2]
    longest = max(h, w)
    if longest > 640:
        scale, interp = 640 / longest, cv2.INTER_AREA
    elif longest < 320:
        scale, interp = 320 / longest, cv2.INTER_CUBIC
    else:
        return img
    return cv2.resize(img, (round(w * scale), round(h * scale)), interpolation=interp)


def analyse(detector, recognizer, path):
    """Return (feature, faces_seen); feature is None when no face was found."""
    img = cv2.imread(str(path))
    if img is None:
        fail(f"Cannot read the image: {path}")
    img = prepare(img)
    h, w = img.shape[:2]
    detector.setInputSize((w, h))
    _, faces = detector.detect(img)
    if faces is None or len(faces) == 0:
        return None, 0
    best = max(faces, key=lambda f: f[14])
    aligned = recognizer.alignCrop(img, best)
    feature = recognizer.feature(aligned).flatten().astype(np.float64)
    feature /= np.linalg.norm(feature)
    return feature, len(faces)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("koha_photo")
    parser.add_argument("kiosk_photo")
    parser.add_argument("--threshold", type=float, default=DEFAULT_THRESHOLD)
    args = parser.parse_args()

    detector, recognizer = load_models()

    reference, _ = analyse(detector, recognizer, args.koha_photo)
    if reference is None:
        print(json.dumps({"status": "reference_unusable", "score": None, "faces": 0}))
        return

    photo, faces = analyse(detector, recognizer, args.kiosk_photo)
    if photo is None:
        print(json.dumps({"status": "no_face", "score": None, "faces": 0}))
        return

    score = float(np.dot(reference, photo))
    status = "matched" if score >= args.threshold else "mismatch"
    print(json.dumps({"status": status, "score": round(score, 3), "faces": faces}))


if __name__ == "__main__":
    main()