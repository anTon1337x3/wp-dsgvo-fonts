"""Converts text set in a (variable) font into one SVG path, so the icon doesn't depend on installed fonts.

    python glyphs.py <font.ttf> <text> <weight> <height>

Prints JSON: {"d": <path data>, "width": <px>, "height": <px>}, scaled so the cap height is <height> px and the
path starts at x=0, with the baseline at y=<height>.
"""

import json
import sys

from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

font_path, text, weight, cap_px = sys.argv[1], sys.argv[2], float(sys.argv[3]), float(sys.argv[4])

font = TTFont(font_path)
if "fvar" in font:
    font = instancer.instantiateVariableFont(font, {"wght": weight})

glyphs = font.getGlyphSet()
cmap = font.getBestCmap()
scale = cap_px / font["OS/2"].sCapHeight

pen = SVGPathPen(glyphs)
bounds = BoundsPen(glyphs)
x = 0
for char in text:
    name = cmap[ord(char)]
    # Font units are y-up; SVG is y-down with the baseline at cap_px.
    transform = (scale, 0, 0, -scale, x * scale, cap_px)
    glyphs[name].draw(TransformPen(pen, transform))
    glyphs[name].draw(TransformPen(bounds, transform))
    x += glyphs[name].width

x_min, y_min, x_max, y_max = bounds.bounds
print(json.dumps({"d": pen.getCommands(), "x": x_min, "y": y_min, "width": x_max - x_min, "height": y_max - y_min}))
