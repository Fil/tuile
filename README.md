# API serveur

## Direct access
- http://tile.rezo.net/[base64:http://www.nnvl.noaa.gov/images/Green/SMNDVI-2012-week25-30000x15000.png]/[signature]/z/x/y.jpg
- http://tile.rezo.net/[base64:http://www.nnvl.noaa.gov/images/Green/SMNDVI-2012-week25-30000x15000.png]/[signature]/


- source is the original megapixel image URL (http/https only)
  transfer as [base64:source]
- signature is MD5(clientID,source)

## JSON access

GET [signature]/[base64:source].json

{
  status: 404 (not found), 503 (downloading / converting), 200 (ok)
  dir: '123456' <= local dir
  url: http://tuile.rezo.net/$dir/
  source: $source
}

http://tuile.rezo.net/$dir/z/x/y <= images

http://tuile.rezo.net/$dir/ <= fullscreen 

http://tile.rezo.net/[base64:source]/[signature]/z/x/y
 => redirect http://tuile.rezo.net/$dir/z/x/y


# Client:

#SET{tileserver,http://localhost/tuile}
<BOUCLE_tile(DATA){source json, #GET{tileserver}/[sig]/[(#HREF|base64_encode)].json}>
[(#VALEUR{status}|=={200}|?{
	<iframe class="fullwidth" width="100%" height="800"
	src="#VALEUR"></iframe>
})]
[(#VALEUR{status}|=={404}|?{
	Image #HREF non trouvée
})]
[(#VALEUR{status}|=={503}|?{
	Image #HREF en cours d'analyse
})]
</BOUCLE_tile>
