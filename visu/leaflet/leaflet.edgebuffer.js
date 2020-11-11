/*PhpDoc:
name: leaflet.edgebuffer.js
title: leaflet.edgebuffer.js - Leaflet 1.0 plugin to support pre-loading tiles outside the current viewport
doc: |
  Voir <a href='https://github.com/TolonUK/Leaflet.EdgeBuffer' target='_blank'>https://github.com/TolonUK/Leaflet.EdgeBuffer</a>
  Source: https://github.com/TolonUK/Leaflet.EdgeBuffer/blob/master/src/leaflet.edgebuffer.js
  downloaded on 24/10/2016
journal: |
  1/11/2016:
    écriture du PhpDoc
  24/10/2016:
    téléchargement
*/

(function (factory, window) {
  // define an AMD module that relies on 'leaflet'
  if (typeof define === 'function' && define.amd) {
    define(['leaflet'], factory);

  // define a Common JS module that relies on 'leaflet'
  } else if (typeof exports === 'object') {
    module.exports = factory(require('leaflet'));
  }

  // attach your plugin to the global 'L' variable
  if (typeof window !== 'undefined' && window.L) {
    window.L.EdgeBuffer = factory(L);
  }
}(function (L) {
  var EdgeBuffer = {
    previousMethods: {
      getTiledPixelBounds: L.GridLayer.prototype._getTiledPixelBounds
    }
  };

  L.GridLayer.include({

    _getTiledPixelBounds : function(center, zoom, tileZoom) {
      var pixelBounds = L.EdgeBuffer.previousMethods.getTiledPixelBounds.call(this, center, zoom, tileZoom);

      // Default is to buffer one tiles beyond the pixel bounds (edgeBufferTiles = 1).
      var edgeBufferTiles = 1;
      if ((this.options.edgeBufferTiles !== undefined) && (this.options.edgeBufferTiles !== null)) {
        edgeBufferTiles = this.options.edgeBufferTiles;
      }

      if (edgeBufferTiles > 0) {
        var pixelEdgeBuffer = edgeBufferTiles * this.options.tileSize;
        pixelBounds = new L.Bounds(pixelBounds.min.subtract([pixelEdgeBuffer, pixelEdgeBuffer]), pixelBounds.max.add([pixelEdgeBuffer, pixelEdgeBuffer]));
      }
      return pixelBounds;
    }
  });

  return EdgeBuffer;
}, window));