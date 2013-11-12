```
Usage:
 archive [--archive-format="..."] [--archive-pattern="..."] [--destination-pattern="..."] [--output-format="..."] [--source-format="..."] [--source-pattern="..."] [--workspace="..."] [--metadata-title[="..."]] [--metadata-artist[="..."]] [--metadata-album[="..."]] [--metadata-year[="..."]] [--metadata-genre[="..."]] [--metadata-comment[="..."]] [--metadata-tracknumber[="..."]] [--metadata-catalogid[="..."]] destination [source]

Arguments:
 destination             Path to directory that will hold the archives.
 source                  Path to directory containing source files. (default: "/home/trivoallan/dev/sources/constructions-incongrues/net.constructions-incongrues.incongrupack")

Options:
 --archive-format        Archive format (default: "zip")
 --archive-pattern       Archive filename pattern (default: "%catalogid%_%outputformat%.%archiveformat%")
 --destination-pattern   Transcoded audio files name pattern (default: "%tracknumber% - %title%.%outputformat%")
 --output-format         Output format. Different bitrates can be achieved using the "format_bitrate" format. eg. mp3_320, ogg_192, etc. (default: ["flac","mp3","ogg"]) (multiple values allowed)
 --source-format         Source files audio format. (default: "flac")
 --source-pattern        Source audio file pattern. Available fields : title, artist, album, year, genre, comment, tracknumber, catalogid (default: "%tracknumber% - %tracktitle%.%sourceformat%")
 --workspace             Directory to hold temporary files (default: "/tmp/incongrupack_52822cbfab0d5")
 --metadata-title        Force value of the "title" field
 --metadata-artist       Force value of the "artist" field
 --metadata-album        Force value of the "album" field
 --metadata-year         Force value of the "year" field
 --metadata-genre        Force value of the "genre" field
 --metadata-comment      Force value of the "comment" field
 --metadata-tracknumber  Force value of the "tracknumber" field
 --metadata-catalogid    Force value of the "catalogid" field
 --help (-h)             Display this help message.
 --quiet (-q)            Do not output any message.
 --verbose (-v|vv|vvv)   Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
 --version (-V)          Display this application version.
 --ansi                  Force ANSI output.
 --no-ansi               Disable ANSI output.
 --no-interaction (-n)   Do not ask any interactive question.
```
