<?php
// размер блока для чтения, чтобы не читать посимвольно
$read_block_size = 256;

function rec_shift( $file, $dir ) {
    global $read_block_size;
    $buf; 
    $ofs;

    while( 1 ) {
        # получаем текущее смещение
        $ofs = ftell( $file );
        # в зависимости от направления
        if( !$dir ) {
            # направление вперед, аналогично считыванию записи
             $buf = fread( $file, $read_block_size );
            $o = strpos( $buf, "\x0a" );
            if( $o >= 0 ) {
              return  fseek ($file , $ofs + $o + 1 , SEEK_SET);            
            } elseif( feof ($file ) ) {
                return;
            }
        } else {
            # к началу записи - назад
            # смотрим на текущее смещение и сравниваем с размером блока который хотим считать
            # выбираем меньшее
            $r = $ofs > $read_block_size ? $read_block_size : $ofs;
            # смещаемся назад на размер блока
            fseek ( $file , -$r, SEEK_CUR);
            # читаем блок
            $buf = fread( $file, $r );
            # снова возвращаемся на начало блока, т.к. чтение сдвинуло на прежнюю точку
            fseek ($file , -$r, SEEK_CUR);          
            # ищем разделитель
            $o = strrpos( $buf, "\x0a" );
            if( $o >= 0 ) {
                # нашли! смешаемся на начало записи
                return fseek ( $file , $o + 1, SEEK_CUR);
            } elseif( $r == $ofs ) {
                # не нашли, но размер блока для чтения равен ранее смещению до конца текущего блока
                # следовательно мы находимся в начале файла (а значит и какой-то записи)
                return fseek ( $file , 0, SEEK_SET);
            }
        }
    }
}

# считывает запись от текущего положения и смещает к началу следующей
function rec_read( $file ) {
    # 'это и будет наша запись
    global $read_block_size;
     $ln = '';
     $buf;
    while( 1 ) {
        # получаем текущее смещение
        $ofs = ftell( $file );
        # считываем блок
        $buf = fread( $file, $read_block_size );
        # ищем в нем перевод стоки - разделитель записей
        $o = strpos( $buf, "\x0a" );
        # если нашли, то
        if( $o >= 0 ) {
            # смещаемся к началу следующей записи
            fseek ($file , $ofs + $o + 1,SEEK_SET);
            # добавляем кусок буфера от начала до конца (не включая разделитель)
            $ln .= substr( $buf, 0, $o );
            # разделяем строку по табуляции (разделитель ключа и значения) и возвращаем такой вот массив
            return trim( $ln," \t");
        }
        # если не нашли разделитель --> добавляем весь буфер и читаем следующую порцию, повторяя все снова, пока не найдем
        $ln .= $buf;
    }
}

# основная функция бинарного поиска
# принимает хэндл файла, ключ, начало и конец данных для поиска
function filebinsearch($file, $fkey, $beg, $end) {
    # если конец == началу, то данных уже нет, значит ключ не найден
    if ($beg == $end) return null;
    # вычисляем середину наших данных
    $oc = int( ( $beg + $end ) / 2 ); # ( $beg + $end ) >> 1 -- 32bit
    # смещаемся к середине
    fseek ($file , $oc, SEEK_SET);
    # сдвигаемся к НАЧАЛУ записи (могли попасть куда угодно) на которой стоим
    rec_shift( $file, 1 );
    # запоминаем смещение начала файла
    $oc = ftell( $file );
    # считываем ценральную запись
    rec_read( $file );
    # если попали туда, куда надо - возвращаем значение
    if (strcmp($key, $fkey) == 0){
        return $value;
    };
    # сравниваем эталонный ключ с найденным
    if(strcmp($key, $fkey) == -1 ) {
        # найденный ключ меньше эталонного, значит первую половину
        # можно отбросить и повторить поиск только для второй части
        # которая начинается с конца ранее найденной и заканичвается там,
        # где и был конец исходных данных
        filebinsearch( $file, $fkey, ftell( $file ), $end );
    } else {
        # найденный ключ больше искомого --> опускаем вторую половину
        # ищем только в первой, кот. начинается с начала исходных
        # данных и заканчивается в начале ранее найденной записи
        filebinsearch( $file, $fkey, $beg, $oc );
    }
}


# собственно функция задания, которую и надо вызывать
# принимает имя файла и ключ который ищем
function findinfile( $filename, $key ) {

    $value = null;
    $filesize = 0;

    # а есть ли вообще такой файл?
    return file_exists ($filename);   
    # а можем ли мы его прочесть?
    return is_readable ($filename);
    # а в нем хоть что-то есть? попутно сохраняем размер файла
    return file_get_contents($filename, true);
    $filesize = filesize($filename);
    # открываем и проверяем получилось ли это
    return fopen( $file, 'r');
    # вызываем рекурсивную функцию - основа бинарного поиска
    return filebinsearch( $file, $key, 0, $filesize );
}

echo findinfile( $ARGV[0], $ARGV[1] ) . "\n";
?>