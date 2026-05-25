from odoo import models, fields, exceptions
from datetime import datetime, timedelta

class JTEmployeesPoints(models.Model):
    _name = 'jtemployees.points'
    _description = 'points for the employees'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    
    name = fields.Text(
        string='Description',
        tracking=True
    )
    
    parent_id         = fields.Many2one('hr.employee', string='Parent Manager', required=True, tracking=True) # parent manager
    employee_id       = fields.Many2one('hr.employee', string='Employee', required=True, tracking=True)
    group_id          = fields.Many2one('jtemployees.points.groups', string='Group', tracking=True)
    date              = fields.Date(string='Date', required=True, tracking=True)
    total_points      = fields.Float(string='Points', compute='_compute_total_points')
    submitted         = fields.Boolean(string='Validated', default=False)
    points_inputs     = fields.One2many('jtemployees.points.inputs', 'points_id', string='Point Inputs', ondelete='cascade', tracking=True)
    
    manager_note      = fields.Text(string='Manager Note', tracking=True)
    hr_note           = fields.Text(string='HR Note', tracking=True)

    def create(self, vals):
        employees = self.env['hr.employee'].browse([vals['employee_id']])
        templates = self.env['jtemployees.points.templates'].sudo().search([])
        vals['parent_id'] = employees[0].parent_id.id
        date_now = datetime.now()
        date_selected_from = date_now - timedelta(days=date_now.day - 1)
        
        vals['date'] = date_selected_from

        # calculate the next month
        month = date_selected_from.month
        month += 1
        if month > 12:
            month = 1
        date_selected_to = str(date_selected_from.year) + "-" + str(month) + "-" + str(date_selected_from.day)
        date_selected_to = datetime.strptime(date_selected_to, '%Y-%m-%d')
        
        exist_points = self.env['jtemployees.points'].sudo().search([
            ('employee_id', '=', vals['employee_id']),
            ('parent_id', '=', vals['parent_id']),
            ('date', '>=', date_selected_from),
            ('date', '<', date_selected_to),
        ])
        
        if len(exist_points) > 0:
            raise exceptions.ValidationError("You have already submitted for this employee for this month")
        
        points = super(JTEmployeesPoints, self).create(vals)
        
        for template in templates:
            self.env['jtemployees.points.inputs'].sudo().create([{
                "points_id": points.id,
                "parent_id": employees[0].parent_id.id,
                "employee_id": employees[0].id,
                "title": template.title,
                "is_hr": template.is_hr,
                "points": 0
            }])
        self.send_notification_to_group("New evaluation", "you have new evaluation")
        return points
    
    def unlink(self):
        for record in self:
            if record.submitted:
                raise exceptions.ValidationError("This evaluation is already submitted")
            inputs = self.env['jtemployees.points.inputs'].sudo().search([("points_id", "=", record.id)])
            for item in inputs:
                item.sudo().unlink()
            super(JTEmployeesPoints, self).unlink()
        return True
    
    
    def test_unlink(self):
        for record in self:
            inputs = self.env['jtemployees.points.inputs'].sudo().search([("points_id", "=", record.id)])
            for item in inputs:
                item.sudo().unlink()
            super(JTEmployeesPoints, self).unlink()
        return True
    
    def write(self, vals):
        for record in self:
            if record.submitted:
                raise exceptions.ValidationError("This evaluation is already submitted")
            super(JTEmployeesPoints, self).write(vals)
        return True
    
    def _compute_total_points(self):
        for record in self:
            points_inputs = self.env['jtemployees.points.inputs'].sudo().search([('points_id', '=', record.id)])
            total_points = 0
            for point_to_sum in points_inputs:
                total_points += point_to_sum.points
            if total_points > 0:
                total_points = total_points / len(points_inputs)
            record.total_points = total_points
        

    def calculate_evaluations(self):
        if not (self.env.user.has_group('jtemployees.group_admin') or not self.env.user.has_group('jtemployees.group_hr_admin') or not self.env.user.has_group('jtemployees.group_hr_manager')):
            raise exceptions.ValidationError("You do not have the required access")
        for points in self:
            employee = points.employee_id
            current_grade = employee.jt_grade
            new_grade = current_grade
            new_current_points = employee.jt_current_points + points.total_points
            if not employee.jt_fixed_salary:
                while new_current_points >= 120:
                    new_current_points = new_current_points - 120
                    # we also upgrade the grades
                    for grade in employee.jt_grade_group.grades:
                        if current_grade.amount >= grade.amount:
                            continue
                        new_grade = grade
                        break
                
            employee.sudo().write({
                "jt_current_points": new_current_points,
                "jt_grade": new_grade.id,
            })
            
            points.sudo().write(
                {
                    "submitted": True
                }
            )
            
            
    def send_notification_to_group(self, subject, body):
        # Assuming the XML ID of the access group is 'jtemployees.group_admin'
        group = self.env.ref('jtemployees.group_admin')
        
        # Get users in the group
        users_in_group = self.env['res.users'].search([('groups_id', '=', group.id)])
        
        # Send notification
        for user in users_in_group:
            self.send_notification(user, subject, body)

    def send_notification(self, user, subject, body):
        #odoobot_id = self.env.ref('base.partner_root').id  # Get OdooBot's partner ID
        print("sending message")
        self.env['bus.bus']._sendone(user.partner_id, 'simple_notification',{
            'title': subject,
            'message': body,
            'sticky': True
                                    })
        




