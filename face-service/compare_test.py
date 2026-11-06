"""Try face matching on your own photos with OpenCV (YuNet detector, SFace recognizer).

Nothing is uploaded anywhere: the photos are read from this computer only.

Usage (from the face-service folder):
  python compare_test.py REFERENCE.jpg OTHER.jpg [OTHER2.jpg ...]
  python compare_test.py --auto PATH_TO_storage\\app\\private
"""
import argparse
import sys
from pathlib import Path

import cv2
import numpy as np

HERE = Path(__file__).resolve().parent
DETECTOR_MODEL = HERE / "models" / "face_detection_yunet_2023mar.onnx"
RECOGNIZER_MODEL = HERE / "models" / "face_recognition_sface_2021dec.onnx"

# OpenCV's own SFace evaluation uses 0.363 on the LFW test set. Harder test sets
# in the same documentation use lower values (0.275 to 0.340), so treat 0.363 as a
# starting point and calibrate it on real photos from this library.
COSINE_THRESHOLD = 0.363
DETECT_THRESHOLD = 0.9


def load_models():
    for model in (DETECTOR_MODEL, RECOGNIZER_MODEL):
        if not model.is_file():
            sys.exit(f"Model file missing: {model}")
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
    img = cv2.imread(str(path))
    if img is None:
        return {"error": "cannot read the image"}
    img = prepare(img)
    h, w = img.shape[:2]
    detector.setInputSize((w, h))
    _, faces = detector.detect(img)
    if faces is None or len(faces) == 0:
        return {"faces": 0}
    best = max(faces, key=lambda f: f[14])
    aligned = recognizer.alignCrop(img, best)
    feature = recognizer.feature(aligned).flatten().astype(np.float64)
    feature /= np.linalg.norm(feature)
    return {"faces": len(faces), "confidence": float(best[14]), "feature": feature}


def describe(result):
    if "error" in result:
        return result["error"]
    return "no face found"


def similarity(a, b):
    return float(np.dot(a["feature"], b["feature"]))


def verdict(score):
    return "same person likely" if score >= COSINE_THRESHOLD else "NOT matching"


def run_pair(detector, recognizer, paths):
    reference = analyse(detector, recognizer, paths[0])
    if "feature" not in reference:
        sys.exit(f"Reference {paths[0].name}: {describe(reference)}")
    for path in paths[1:]:
        other = analyse(detector, recognizer, path)
        if "feature" not in other:
            print(f"{path.name}: {describe(other)}")
            continue
        score = similarity(reference, other)
        print(f"{path.name}: score {score:.3f} ({verdict(score)}), faces seen {other['faces']}")


def run_auto(detector, recognizer, root):
    koha = {}
    for photo in sorted((root / "koha-photos").glob("*.jpg")):
        koha[photo.stem] = analyse(detector, recognizer, photo)

    print("Koha photos:")
    for patron, result in koha.items():
        status = "usable" if "feature" in result else describe(result)
        print(f"  patron {patron}: {status}")

    kiosk_files = sorted(
        list((root / "patron-verification-captures").glob("verification-*.jpg"))
        + list((root / "patron-verification-captures").glob("verification-*.png"))
    )
    print(f"\nKiosk photos on this computer: {len(kiosk_files)}")

    for photo in kiosk_files:
        patron = photo.name.split("-")[1]
        result = analyse(detector, recognizer, photo)
        if "feature" not in result:
            print(f"  {photo.name}: patron {patron}, {describe(result)}")
            continue

        own = koha.get(patron)
        if own is not None and "feature" in own:
            own_text = f"vs own Koha photo {similarity(result, own):.3f}"
        else:
            own_text = "no usable Koha photo for this patron"

        others = [
            (other_patron, similarity(result, other))
            for other_patron, other in koha.items()
            if other_patron != patron and "feature" in other
        ]
        if others:
            closest_patron, closest = max(others, key=lambda item: item[1])
            other_text = f", closest other patron {closest_patron} {closest:.3f}"
        else:
            other_text = ""

        print(f"  {photo.name}: patron {patron}, {own_text}{other_text}")

    print(f"\nThreshold used: {COSINE_THRESHOLD} (scores at or above it count as the same person).")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--auto", metavar="PRIVATE_DIR", help="folder that holds koha-photos and patron-verification-captures")
    parser.add_argument("images", nargs="*", help="reference image first, then images to compare with it")
    args = parser.parse_args()

    detector, recognizer = load_models()

    if args.auto:
        run_auto(detector, recognizer, Path(args.auto))
    elif len(args.images) >= 2:
        run_pair(detector, recognizer, [Path(p) for p in args.images])
    else:
        parser.error("give --auto FOLDER, or a reference image followed by images to compare")


if __name__ == "__main__":
    main()