# Export de la comhisto en Pgsql en GeoJSON + compression
ogr2ogr \
  -f GeoJSON comhistog3.geojson -nlt MULTIPOLYGON -lco RFC7946=YES -lco ID_FIELD=id -lco WRITE_BBOX=YES -lco COORDINATE_PRECISION=5 \
  -lco DESCRIPTION='Référentiel historique des communes au 1/1/2020, voir la doc. sur https://github.com/benoitdavidfr/comhisto' \
  PG:'host=172.17.0.4 port=5432 dbname=gis user=docker password=docker' "comhistog3"
7z a comhistog3.7z comhistog3.geojson
