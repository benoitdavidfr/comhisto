# Export de la comhisto en Pgsql en GeoJSON + compression
ogr2ogr \
  -f GeoJSON comhistog3.geojson \
  PG:'host=172.17.0.4 port=5432 dbname=gis user=docker password=docker' "comhistog3"  -lco RFC7946=YES
7z a comhistog3.7z comhistog3.geojson
