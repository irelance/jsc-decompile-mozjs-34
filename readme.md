#1.Summary
This project is a javascript bytecode decoder for mozilla spider-monkey version 34.

This version may decompile jsc file compile by cocos-2dx.

It would not work for different version of Mozilla spider-monkey, for its opcode defined different for each version.

#2.Usage
##2.1.Install PHP and Composer
If you are familiar with php, you can skip this part.

install php
```
# ubuntu
$ sudo apt install php

# mac
$ brew install php

# windows
# just google an binary one
```
install composer
>see https://getcomposer.org/download/

install this project
```
$ cd path/to/project
$ composer install
```

##2.2.decompile *.jsc file
```
$ cd path/to/project
$ php run.php *.jsc > path/to/decompile.txt
```

#3.Besides
This project is not complete yet. Decompile result is not a runable file. Some local variables are auto generate, for the compiler discards local variables.
