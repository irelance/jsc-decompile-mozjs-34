#1.Summary

This project is a javascript bytecode decoder for mozilla spider-monkey version 34.

This version may decompile jsc file compile by cocos-2dx.

It would not work for different version of Mozilla spider-monkey (without shell of course), for its opcode defined different for each version.

#2.Usage

##2.1.Install PHP and Composer

If you are familiar with php, you can skip this part.

install php7.0
```
# ubuntu
$ sudo apt install php7.0

# mac
$ brew install php7.0

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
#if this didn't work, you can also try below command to get the bitcode
$ php scan.php > path/to/scan.txt
```

#3.Besides

This project is not complete yet.

- A Fatal Bug was found when decompile with a deep context

Decompile result is not a runable file.
Some local variables are auto generate, for the compiler discards local variables.
