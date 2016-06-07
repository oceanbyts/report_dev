<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/student")
 */
class StudentController extends Controller
{

    /**
     * @Route("/")
     */
    public function getBaseStudentForm()
    {
        $form = $this->createFormBuilder(['label' => 'Type your message here'],['attr' => ['id' => 'findStudent_id']])
            ->add('std_ID',NumberType::class, [
                'label' => 'Номер зачётной книжки',
//                'attr' => [
//                    'maxlength' => 6,
//                    'pattern' => '\d{1,6}',
//                ],
                'attr' => [
                    'data-help' => 'некоторый текст для подсказки',
                ],
                'required' => false,
            ])
            ->add('std_SName', TextType::class, [
                'label' => 'Фамилия',
//                'attr' => [
//                    'pattern' => '[а-яёА-ЯЁ]+',
//                ],
                'required' => false,
                'attr' => [
                    'value' => 'Агеев',
                ]
            ])
            ->add('std_FName', TextType::class, [
                'label' => 'Имя',
                'required' => false,
            ])
            ->add('std_PName', TextType::class, [
                'label' => 'Отчество',
                'required' => false,
            ])
            ->add('std_Group', TextType::class, [
                'label' => 'Группа',
                'required' => false,
            ])
            ->add('nonActive',CheckboxType::class, [
                'label' => 'Искать среди неактивных',
                'required' => false,
            ])
            ->add('clear', ResetType::class, [
                'label' =>'Очистить',
                'attr' => [
                    'first',
                    'where' => 'right',
                ],
            ])
            ->add('send', SubmitType::class, [
                'label'=>'Найти',
                'attr' => [
                    'last',
                ],
            ])
            ->getForm();

/*            $form->get('std_ID')->addError(new FormError('wqwq'));
            $form->get('std_SName')->addError(new FormError('111'));*/

        return $this->render(':students:student.base.html.twig',[
            'form' => $form->createView(),
        ]);
    }
    /**
     * @Route("/findStudents1/", name="find_students_by_Server")
     * @Method({"POST","GET"})
     */
    public function findStudentsByServer(Request $request){

        $allColumns=['S.BOOKNUMBER_P','pas.LASTNAME_P','pas.FIRSTNAME_P','pas.MIDDLENAME_P','G.TITLE_P'];

        $clmNum = $request->request->get('order')[0]['column'];
        if(array_key_exists($clmNum,$allColumns)){
            $clm = iconv('UTF-8', 'Windows-1251', $allColumns[$clmNum]);
        } else {
            $clm = iconv('UTF-8', 'Windows-1251', 'S.BOOKNUMBER');
        }

        //var_dump($clm);
        $startNumber=$request->request->get('start')*$request->request->get('length')+1; //Вчисляем номер первого элемента для выдачи на страницу
        $endNumber=($request->request->get('start')+1)*$request->request->get('length')+1; //Вычисляем номер последнего элемента для выдачи на страницу
        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $sql =
            "
            SELECT * FROM (
                select
                    -- CAST нужно для преобразования к строке, т.к. иначе число выдаётся в экспоненциальная форме
                    isnull(cast(S.ID as VARCHAR(20)) ,'') as 'DT_RowId' -- внутренний номер записи
                    ,isnull(S.BOOKNUMBER_P,'') as 'id'  --Номер зачётной книжки
                    ,isnull(pas.LASTNAME_P,'') as 'sname' --Фамилия
                    ,isnull(pas.FIRSTNAME_P,'') as 'fname' --Имя
                    ,isnull(pas.MIDDLENAME_P,'') as 'pname' --Отчество
                    ,isnull(G.TITLE_P,'')as 'grp'--Номер группы
                    , ROW_NUMBER() OVER (ORDER By ".$clm.") as RowNum --Нужно для последующей разбивки на страницы
                from PERSON_T [P]
                    LEFT join identitycard_t pas on P.IDENTITYCARD_ID = pas.ID --Паспорт
                inner join personrole_t PR on PR.PERSON_ID = P.ID
                 LEFT join STUDENT_T S on S.ID = PR.ID
                    left join GROUP_T G on G.ID = S.GROUP_ID
                where 
                S.BOOKNUMBER_P like
                 CASE 
                    WHEN :id = '' 
                      THEN S.BOOKNUMBER_P
                    ELSE '%'+:id+'%'
                 END 
                  AND pas.LASTNAME_P like
                  CASE
                    WHEN :sname = ''
                      THEN pas.LASTNAME_P
                    ELSE '%'+:sname+'%'
                  END
                 AND pas.FIRSTNAME_P like
                  CASE
                    WHEN :fname = ''
                      THEN pas.FIRSTNAME_P
                    ELSE '%'+:fname+'%'
                  END
                 AND pas.MIDDLENAME_P like
                  CASE
                    WHEN :pname = ''
                      THEN pas.MIDDLENAME_P
                    ELSE '%'+:pname+'%'
                  END
                 AND G.TITLE_P like
                  CASE
                    WHEN :grp = ''
                      THEN G.TITLE_P
                    ELSE '%'+:grp+'%'
                  END
                  AND s.ID is not null --Не ищем студентов без идентификатора
              ) as Res WHERE RowNum >=".$startNumber." AND RowNum <".$endNumber."
              ORDER BY RowNum";
        $sql =  mb_convert_encoding($sql,'Windows-1251', 'UTF-8');
        $statement = $connection->prepare($sql);
        $statement->bindValue(':id', iconv('UTF-8', 'Windows-1251', $request->request->get('id')), 'text');
        $statement->bindValue(':sname', iconv('UTF-8', 'Windows-1251', $request->request->get('sname')),'text');
        $statement->bindValue(':fname', iconv('UTF-8', 'Windows-1251', $request->request->get('fname')),'text');
        $statement->bindValue(':pname', iconv('UTF-8', 'Windows-1251', $request->request->get('pname')),'text');
        $statement->bindValue(':grp', iconv('UTF-8', 'Windows-1251', $request->request->get('grp')),'text');
        $statement->execute();
        $rowsarray = $statement->fetchAll();

        $rowsarray=array_map([$this,'cp1251TOutf8'],$rowsarray);  //Преобразование к UTF-8

        //Формирование ответа для таблицы
        $result =[
            'draw'=> 1,
            'recordsTotal'=> count($rowsarray),
            'recordsFiltered' => count($rowsarray),
            'data' => $rowsarray,
        ];

        return new JsonResponse($result);
    }

    /**
     * @Route("/findStudents2/", name="find_students_by_Client")
     * @Method({"POST","GET"})
     */
    public function findStudentsByClient(Request $request){

        $em = $this->getDoctrine()->getManager();
        $connection = $em->getConnection();
        $sql =
            "    select
                    -- CAST нужно для преобразования к строке, т.к. иначе число выдаётся в экспоненциальная форме
                    isnull(cast(S.ID as VARCHAR(20)) ,'') as 'DT_RowId' -- внутренний номер записи
                    ,isnull(S.BOOKNUMBER_P,'') as 'id'  --Номер зачётной книжки
                    ,isnull(pas.LASTNAME_P,'') as 'sname' --Фамилия
                    ,isnull(pas.FIRSTNAME_P,'') as 'fname' --Имя
                    ,isnull(pas.MIDDLENAME_P,'') as 'pname' --Отчество
                    ,isnull(G.TITLE_P,'')as 'grp'--Номер группы
                    , ROW_NUMBER() OVER (ORDER By S.BOOKNUMBER_P) as RowNum --Нужно для последующей разбивки на страницы
                from PERSON_T [P]
                    LEFT join identitycard_t pas on P.IDENTITYCARD_ID = pas.ID --Паспорт
                inner join personrole_t PR on PR.PERSON_ID = P.ID
                 LEFT join STUDENT_T S on S.ID = PR.ID
                    left join GROUP_T G on G.ID = S.GROUP_ID
                where 
                S.BOOKNUMBER_P like
                 CASE 
                    WHEN :id = '' 
                      THEN S.BOOKNUMBER_P
                    ELSE '%'+:id+'%'
                 END 
                  AND pas.LASTNAME_P like
                  CASE
                    WHEN :sname = ''
                      THEN pas.LASTNAME_P
                    ELSE '%'+:sname+'%'
                  END
                 AND pas.FIRSTNAME_P like
                  CASE
                    WHEN :fname = ''
                      THEN pas.FIRSTNAME_P
                    ELSE '%'+:fname+'%'
                  END
                 AND pas.MIDDLENAME_P like
                  CASE
                    WHEN :pname = ''
                      THEN pas.MIDDLENAME_P
                    ELSE '%'+:pname+'%'
                  END
                 AND G.TITLE_P like
                  CASE
                    WHEN :grp = ''
                      THEN G.TITLE_P
                    ELSE '%'+:grp+'%'
                  END
                  AND s.ID is not null --Не ищем студентов без идентификатора";
        $sql =  mb_convert_encoding($sql,'Windows-1251', 'UTF-8');
        $statement = $connection->prepare($sql);
        $statement->bindValue(':id', iconv('UTF-8', 'Windows-1251', $request->request->get('id')), 'text');
//        $statement->bindValue(':sname', iconv('UTF-8', 'CP1251', $request->request->get('sname')),'text');
        $statement->bindValue(':sname', mb_convert_encoding($request->request->get('sname'), 'CP1251', 'UTF8'),'text');
        $statement->bindValue(':fname', iconv('UTF-8', 'CP1251', $request->request->get('fname')),'text');
        $statement->bindValue(':pname', iconv('UTF-8', 'Windows-1251', $request->request->get('pname')),'text');
        $statement->bindValue(':grp', iconv('UTF-8', 'Windows-1251', $request->request->get('grp')),'text');
        $statement->execute();
        $rowsarray = $statement->fetchAll();

        $rowsarray=array_map([$this,'cp1251TOutf8'],$rowsarray);  //Преобразование к UTF-8

        //Формирование ответа для таблицы
        $result =[
            'draw'=> 1,
            'recordsTotal'=> count($rowsarray),
            'recordsFiltered' => count($rowsarray),
            'data' => $rowsarray,
        ];

        return new JsonResponse($result);
    }

    //Преобразование к UTF-8
    private function  cp1251TOutf8($data){
        $tempArray=Array();
        if($data) {

            if(is_array($data)) {
                $tempArray=array_map([$this,'cp1251TOutf8'],$data);
            } else {

                return iconv('Windows-1251', 'UTF-8', $data);
            }
            return $tempArray;
        }

    }

    /**
     * @Route("/{id}", name="get_students_data")
     * @Method("GET")
     */
    public function getStudentData($id){
        return $this->render(':students:showPersonalData.html.twig',[
            'id' => $id,
        ]);
    }

    /**
     * @Route("/info/php", name="phpinfo")
     * @Method("GET")
     */
    public function getPhpData(){
        return phpinfo();
    }

}
