import io
import json
import os
import sys
import unittest
from unittest.mock import MagicMock, patch

# If cv2 is not installed in the current Python environment, mock it before importing match.py
if "cv2" not in sys.modules:
    try:
        import cv2
    except ImportError:
        mock_cv2 = MagicMock()
        sys.modules["cv2"] = mock_cv2

# Ensure face-service directory is on sys.path
HERE = os.path.dirname(os.path.abspath(__file__))
if HERE not in sys.path:
    sys.path.insert(0, HERE)

import match


class TestMatchPy(unittest.TestCase):
    def test_fail_prints_json_and_exits(self):
        with patch("sys.stdout", new=io.StringIO()) as fake_out:
            with self.assertRaises(SystemExit) as cm:
                match.fail("Something went wrong")
            self.assertEqual(cm.exception.code, 1)

        output = fake_out.getvalue().strip()
        data = json.loads(output)
        self.assertEqual(data["status"], "error")
        self.assertEqual(data["message"], "Something went wrong")

    def test_load_models_fails_if_models_missing(self):
        with patch("sys.stdout", new=io.StringIO()):
            with patch.object(match, "DETECTOR_MODEL", match.HERE / "nonexistent_detector.onnx"):
                with self.assertRaises(SystemExit) as cm:
                    match.load_models()
                self.assertEqual(cm.exception.code, 1)

    def test_cosine_similarity_threshold(self):
        import numpy as np

        vec1 = np.array([1.0, 0.0, 0.0])
        vec2 = np.array([1.0, 0.0, 0.0])
        score_same = float(np.dot(vec1, vec2))
        self.assertAlmostEqual(score_same, 1.0)
        self.assertTrue(score_same >= match.DEFAULT_THRESHOLD)

        vec3 = np.array([0.0, 1.0, 0.0])
        score_diff = float(np.dot(vec1, vec3))
        self.assertAlmostEqual(score_diff, 0.0)
        self.assertFalse(score_diff >= match.DEFAULT_THRESHOLD)


if __name__ == "__main__":
    unittest.main()
