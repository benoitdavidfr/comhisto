# Export comhistog3 et elit
# Fichier comhisto/export/Makefile - Mis à jour le 9/11/2020
all: comhistog3.7z elit.7z
comhistog3.7z: comhistog3.geojson
	7z a comhistog3.7z comhistog3.geojson
comhistog3.geojson: ../croise2/comhistog3
	ogr2ogr \
    -f GeoJSON comhistog3.geojson -nlt MULTIPOLYGON -lco RFC7946=YES -lco ID_FIELD=id -lco WRITE_BBOX=YES -lco COORDINATE_PRECISION=5 \
    -lco DESCRIPTION='Référentiel historique des communes au 1/1/2020, voir la doc. sur https://github.com/benoitdavidfr/comhisto' \
    PG:'host=172.17.0.4 port=5432 dbname=gis user=docker password=docker' "comhistog3"
../croise2/comhistog3:
	cd ../croise2 ; make comhistog3
elit.7z: elit.geojson
	7z a elit.7z elit.geojson
elit.geojson: ../croise2/comhistog3
	ogr2ogr \
    -f GeoJSON elit.geojson -lco RFC7946=YES -lco ID_FIELD=cinsee -lco WRITE_BBOX=YES -lco COORDINATE_PRECISION=5 \
    -lco DESCRIPTION='Eléments administratifs intemporels au 1/1/2020, voir la doc. sur https://github.com/benoitdavidfr/comhisto' \
    PG:'host=172.17.0.4 port=5432 dbname=gis user=docker password=docker' "elit"
